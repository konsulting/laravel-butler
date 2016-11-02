@unless(empty(\Butler::providers()))
    <div class="row">
        <div class="col-sm-4 col-sm-offset-4 text-center">
            <h4>Or, login with</h4>
            @foreach (\Butler::providers() as $code => $details)
                <a href="{{ route('butler.redirect', $code) }}" class="btn btn-default btn-block {{ $details->class }}">
                    @if ($details->icon)
                        <i class="{{ $details->icon }}"></i>
                    @endif
                    {{ $details->name }}
                </a>
            @endforeach
        </div>
    </div>
@endunless
