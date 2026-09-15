<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleReminder;
use App\Models\VehicleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::forUser($request->user())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        // Anything needing attention rises to the top: the page exists to
        // answer "is something due?" before "what do I own?".
        $vehicles = $vehicles->sortBy([
            fn(Vehicle $a, Vehicle $b) => ($b->needsAttention() ? 1 : 0) <=> ($a->needsAttention() ? 1 : 0),
            fn(Vehicle $a, Vehicle $b) => strcmp($a->name, $b->name),
        ])->values();

        return view('vehicles.index', compact('vehicles'));
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        $vehicle->load(['reminders', 'services', 'expenses']);

        return view('vehicles.show', [
            'vehicle'   => $vehicle,
            'reminders' => $vehicle->reminders->whereNull('completed_at')
                ->each->setRelation('vehicle', $vehicle),
            'services'  => $vehicle->services,
            'breakdown' => $vehicle->expenseBreakdown(Carbon::today()->subYear()),
        ]);
    }

    public function create()
    {
        return view('vehicles.form', ['vehicle' => new Vehicle(), 'editing' => false]);
    }

    public function edit(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        return view('vehicles.form', compact('vehicle') + ['editing' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $vehicle = Vehicle::create([
            ...$data,
            'created_by' => $request->user()->id,
            'family_id'  => ($data['is_shared'] ?? false) ? $request->user()->family_id : null,
            'photo_path' => $this->storePhoto($request),
        ]);

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', __('messages.vehicle_saved'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        $data = $this->validated($request);
        $data['family_id'] = ($data['is_shared'] ?? false) ? $request->user()->family_id : null;

        if ($photo = $this->storePhoto($request)) {
            // One photo per vehicle: the replaced file is deleted rather than
            // orphaned on disk.
            if ($vehicle->photo_path) {
                Storage::disk('public')->delete($vehicle->photo_path);
            }
            $data['photo_path'] = $photo;
        }

        $vehicle->update($data);

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', __('messages.vehicle_saved'));
    }

    public function destroy(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        if ($vehicle->photo_path) {
            Storage::disk('public')->delete($vehicle->photo_path);
        }

        $vehicle->delete();

        return redirect()->route('vehicles.index')
            ->with('success', __('messages.vehicle_deleted'));
    }

    /**
     * Update the odometer on its own.
     *
     * The reading changes constantly while everything else about a vehicle
     * almost never does, so it gets an inline control instead of sending people
     * through the edit form.
     */
    public function updateOdometer(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        $data = $request->validate([
            'odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
        ]);

        $vehicle->update([
            'odometer_km'      => $data['odometer_km'],
            'odometer_read_at' => Carbon::today(),
        ]);

        return back()->with('success', __('messages.vehicle_odometer_updated'));
    }

    // ── Reminders ────────────────────────────────────────────────────────

    public function storeReminder(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        $data = $request->validate([
            'label'           => ['required', 'string', 'max:120'],
            'due_date'        => ['nullable', 'date'],
            'due_km'          => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'interval_km'     => ['nullable', 'integer', 'min:1', 'max:999999'],
            'note'            => ['nullable', 'string', 'max:255'],
        ]);

        // A reminder due on neither a date nor a mileage can never come due.
        if (empty($data['due_date']) && empty($data['due_km'])) {
            return back()->withErrors(['due_date' => __('messages.vehicle_reminder_needs_due')])->withInput();
        }

        $vehicle->reminders()->create($data);

        return back()->with('success', __('messages.vehicle_reminder_added'));
    }

    /**
     * Tick a reminder off, rolling the recurring ones forward.
     *
     * An oil change every 15.000 km is not finished when it is done — it is due
     * again 15.000 km later. Closing the old row and opening the next keeps the
     * history intact instead of mutating one row forever.
     */
    public function completeReminder(Request $request, Vehicle $vehicle, VehicleReminder $reminder)
    {
        $this->authorizeVehicle($request, $vehicle);
        abort_unless($reminder->vehicle_id === $vehicle->id, 404);

        $reminder->update(['completed_at' => now()]);

        if ($reminder->interval_months || $reminder->interval_km) {
            $vehicle->reminders()->create([
                'label'           => $reminder->label,
                'note'            => $reminder->note,
                'interval_months' => $reminder->interval_months,
                'interval_km'     => $reminder->interval_km,
                'due_date'        => $reminder->interval_months
                    ? Carbon::today()->addMonths($reminder->interval_months)
                    : null,
                'due_km'          => $reminder->interval_km
                    ? (int) $vehicle->odometer_km + (int) $reminder->interval_km
                    : null,
            ]);
        }

        return back()->with('success', __('messages.vehicle_reminder_done'));
    }

    public function destroyReminder(Request $request, Vehicle $vehicle, VehicleReminder $reminder)
    {
        $this->authorizeVehicle($request, $vehicle);
        abort_unless($reminder->vehicle_id === $vehicle->id, 404);

        $reminder->delete();

        return back()->with('success', __('messages.vehicle_reminder_deleted'));
    }

    // ── Services and expenses ────────────────────────────────────────────

    public function storeService(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        $data = $request->validate([
            'performed_at'  => ['required', 'date'],
            'odometer_km'   => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'shop'          => ['nullable', 'string', 'max:120'],
            'total'         => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'lines'         => ['nullable', 'array', 'max:30'],
            'lines.*.what'  => ['nullable', 'string', 'max:120'],
            'lines.*.cost'  => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);

        // Blank rows are what an "add another line" button leaves behind.
        $data['lines'] = collect($data['lines'] ?? [])
            ->filter(fn($l) => filled($l['what'] ?? null) || filled($l['cost'] ?? null))
            ->values()
            ->all() ?: null;

        $data['total'] = $data['total'] ?? 0;

        $vehicle->services()->create($data);

        // A service reading is also an odometer reading, and a fresher one.
        if (! empty($data['odometer_km']) && $data['odometer_km'] > $vehicle->odometer_km) {
            $vehicle->update([
                'odometer_km'      => $data['odometer_km'],
                'odometer_read_at' => $data['performed_at'],
            ]);
        }

        return back()->with('success', __('messages.vehicle_service_added'));
    }

    public function destroyService(Request $request, Vehicle $vehicle, VehicleService $service)
    {
        $this->authorizeVehicle($request, $vehicle);
        abort_unless($service->vehicle_id === $vehicle->id, 404);

        $service->delete();

        return back()->with('success', __('messages.vehicle_service_deleted'));
    }

    public function storeExpense(Request $request, Vehicle $vehicle)
    {
        $this->authorizeVehicle($request, $vehicle);

        $data = $request->validate([
            'category'    => ['required', Rule::in(VehicleExpense::CATEGORIES)],
            'amount'      => ['required', 'numeric', 'min:0', 'max:999999'],
            'spent_at'    => ['required', 'date'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'litres'      => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle->expenses()->create($data + ['created_by' => $request->user()->id]);

        if (! empty($data['odometer_km']) && $data['odometer_km'] > $vehicle->odometer_km) {
            $vehicle->update([
                'odometer_km'      => $data['odometer_km'],
                'odometer_read_at' => $data['spent_at'],
            ]);
        }

        return back()->with('success', __('messages.vehicle_expense_added'));
    }

    public function destroyExpense(Request $request, Vehicle $vehicle, VehicleExpense $expense)
    {
        $this->authorizeVehicle($request, $vehicle);
        abort_unless($expense->vehicle_id === $vehicle->id, 404);

        $expense->delete();

        return back()->with('success', __('messages.vehicle_expense_deleted'));
    }

    // ── Shared ───────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'type'             => ['required', Rule::in(Vehicle::TYPES)],
            'brand'            => ['nullable', 'string', 'max:80'],
            'model'            => ['nullable', 'string', 'max:80'],
            'plate'            => ['nullable', 'string', 'max:20'],
            'year'             => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'variant'          => ['nullable', 'string', 'max:40'],
            'odometer_km'      => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'purchase_date'    => ['nullable', 'date'],
            'purchase_km'      => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'insurer'          => ['nullable', 'string', 'max:80'],
            'insurance_due'    => ['nullable', 'date'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'is_active'        => ['nullable', 'boolean'],
            'is_shared'        => ['nullable', 'boolean'],
            'photo'            => ['nullable', 'image', 'max:8192'],
        ]);

        unset($data['photo']);

        $data['odometer_km'] = $data['odometer_km'] ?? 0;
        $data['is_active']   = (bool) ($data['is_active'] ?? true);
        $data['is_shared']   = (bool) ($data['is_shared'] ?? false);

        return $data;
    }

    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('vehicles', 'public')
            : null;
    }

    /** Sharing is the same rule as bills: mine, or my family's shared ones. */
    private function authorizeVehicle(Request $request, Vehicle $vehicle): void
    {
        $visible = Vehicle::forUser($request->user())->whereKey($vehicle->id)->exists();

        abort_unless($visible, 403);
    }
}
