@php $actor = auth()->user(); @endphp
<div class="table-card workspace-identity" data-workspace-identity style="position:sticky;top:0;z-index:2;padding:16px">
    @include('admin.users._identity')
</div>
<div data-workspace data-workspace-guard-tabs>
    <nav class="tabs workspace-nav" data-workspace-nav>
        <a class="tab" href="#profile" data-workspace-section="profile">{{ __('workspace.profile') }}</a>
        @foreach($panels as $key => $definition)
            <a class="tab" href="{{ route('admin.users.workspace.panel',[$user,$key,'manage'=>$manage ? 1 : 0,'return_to'=>\App\Support\SaveAction::returnTo()]).'#'.$key }}" data-workspace-related="{{ $key }}" data-related-url="{{ route('admin.users.workspace.panel',[$user,$key,'manage'=>$manage ? 1 : 0,'return_to'=>\App\Support\SaveAction::returnTo()]) }}">{{ __($definition->title) }}</a>
        @endforeach
    </nav>
    <div data-workspace-form>
        @if($manage)
            <form method="POST" action="{{ route('admin.users.update',$user) }}">
                @csrf @method('PUT')<input type="hidden" name="_workspace_profile" value="1">
                <x-admin.form-section :title="__('workspace.profile')" columns="2">
                    @foreach(['name','email','phone','username'] as $field)
                    <div><label for="{{ $field }}">{{ __('workspace.'.$field) }}</label><input class="input" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$user->$field) }}" @required(in_array($field,['name','email'])) type="{{ $field === 'email' ? 'email' : 'text' }}"></div>
                    @endforeach
                    <div><label>{{ __('workspace.language') }}</label><select class="select" name="language">@foreach(['English','Arabic'] as $language)<option @selected(old('language',$user->language ?? 'English') === $language)>{{ $language }}</option>@endforeach</select></div>
                </x-admin.form-section>
                <x-admin.form-actions :cancel="route('admin.users.index')" :save-new="$actor->hasPermission('Users','create')"/>
            </form>
        @else
            <div class="table-card" style="padding:16px"><h3>{{ __('workspace.readonly') }}</h3>
                @foreach(['name','email','phone','username','language','employee_id','joining_date','contract_type','employee_classification'] as $field)
                <p>{{ __('workspace.'.$field) }}: {{ $user->$field ?: '-' }}</p>
                @endforeach
            </div>
        @endif
    </div>
    <x-admin.workspace-host :close-url="\App\Support\SaveAction::cancelUrl(route('admin.users.index'))"/>
</div>
