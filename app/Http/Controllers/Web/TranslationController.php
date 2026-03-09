<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    protected function authorizeAdmin()
    {
        $admin = env('ADMIN_EMAIL');
        abort_unless(auth()->check() && auth()->user()->email === $admin, 403, 'Admins only');
    }

    public function index()
    {
        $this->authorizeAdmin();
        $translations = Translation::orderBy('group')->orderBy('locale')->orderBy('key')->paginate(50);
        return view('translations.index', compact('translations'));
    }

    public function create()
    {
        $this->authorizeAdmin();
        return view('translations.form');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'group' => ['required', 'string', 'max:60'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
        ]);
        Translation::create($data);
        return redirect()->route('translations.index')->with('success', 'Translation created.');
    }

    public function edit(Translation $translation)
    {
        $this->authorizeAdmin();
        return view('translations.form', compact('translation'));
    }

    public function update(Request $request, Translation $translation)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'group' => ['required', 'string', 'max:60'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
        ]);
        $translation->update($data);
        return redirect()->route('translations.index')->with('success', 'Translation updated.');
    }

    public function destroy(Translation $translation)
    {
        $this->authorizeAdmin();
        $translation->delete();
        return back()->with('success', 'Translation deleted.');
    }
}

