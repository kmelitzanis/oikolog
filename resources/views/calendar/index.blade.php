@extends('layouts.app')
@section('title', 'Calendar')

@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-title">Calendar</h1>
            <p class="page-subtitle">Your upcoming bills and due dates</p>
        </div>
        <a href="{{ route('bills.create') }}" class="btn btn-primary">Add Bill</a>
    </div>

    <div class="card" style="padding:16px;">
        <div id="calendar"></div>
    </div>

@endsection

@push('head')
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css' rel='stylesheet'/>
@endpush

@push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                headerToolbar: {left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek,listWeek'},
                events: {
                    url: '/bills/events',
                    method: 'GET',
                    extraParams: {},
                    failure: function () {
                        console.error('Failed to load events');
                    }
                },
                eventClick: function (info) {
                    if (info.event.url) {
                        window.location = info.event.url;
                        info.jsEvent.preventDefault();
                    }
                }
            });

            calendar.render();
        });
    </script>
@endpush

