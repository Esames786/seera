@props(['label', 'value' => null])
<fieldset class="rating-picker">
    <legend>{{ __($label) }}</legend>
    <div class="rating-options">
        @foreach (['' => 'not_rated', 'Green' => 'green', 'Amber' => 'amber', 'Red' => 'red'] as $rating => $key)
            <label class="rating-option">
                <input type="radio" name="rating" value="{{ $rating }}" @checked((string) old('rating', $value) === (string) $rating)/>
                <span class="rating-swatch rating-{{ $key }}" aria-hidden="true"></span>
                <span>{{ __('ui.'.$key) }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
