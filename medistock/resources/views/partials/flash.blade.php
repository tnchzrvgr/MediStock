{{-- shows a message that the page before saved (example: "Medicine added") --}}
@if (session('flash'))
    <div class="flash {{ session('flash')['type'] == 'error' ? 'flash-error' : 'flash-success' }}">{{ session('flash')['text'] }}</div>
@endif
