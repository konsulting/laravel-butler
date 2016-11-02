@if(session('status'))
    <div class="alert alert-{{ session('status.type', 'info') }}">
        <p>{{ session('status.content', session('status', 'No message provided')) }}</p>
    </div>
@endif
