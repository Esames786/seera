@props(['owner' => null, 'kind' => 'user', 'readonly' => false])
<div class="full" data-profile-photo>
    @if ($owner?->profile_photo_path)
        <img data-photo-preview src="{{ route('admin.profile-photos.show', [$kind, $owner->id]) }}" alt="{{ __('feedback.profile_photo') }}" width="80" height="80" style="object-fit:cover;border-radius:50%;max-width:100%"/>
    @else
        <img data-photo-preview hidden alt="{{ __('feedback.profile_photo') }}" width="80" height="80" style="object-fit:cover;border-radius:50%;max-width:100%"/>
    @endif
    @unless ($readonly)
        <label for="{{ $kind }}-profile-photo">{{ __('feedback.upload_photo') }}</label>
        <input class="input" type="file" id="{{ $kind }}-profile-photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-photo-input/>
        <div class="small">{{ __('feedback.photo_help') }}</div>
        @error('profile_photo')<div class="field-error">{{ $message }}</div>@enderror
        @if ($owner?->profile_photo_path)
            <label><input type="checkbox" name="remove_profile_photo" value="1" @checked(old('remove_profile_photo'))/> {{ __('feedback.remove_photo') }}</label>
        @endif
    @endunless
</div>
