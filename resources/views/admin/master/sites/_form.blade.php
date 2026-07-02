@php /** @var \App\Models\Site|null $site */ $site = $site ?? null; @endphp

<form method="POST" action="{{ $site ? route('admin.master.sites.update', $site) : route('admin.master.sites.store') }}">
    @csrf
    @if ($site) @method('PUT') @endif

    <div class="split even">
        <div>
            <x-admin.form-section title="Site Information" columns="2">
                <div><label for="name">Site Name *</label><input id="name" name="name" class="input" value="{{ old('name', $site?->name) }}" required/></div>
                <div><label for="code">Site Code *</label><input id="code" name="code" class="input" value="{{ old('code', $site?->code) }}" placeholder="SITE-A" required/></div>
                <div>
                    <label for="project_id">Project *</label>
                    <select id="project_id" name="project_id" class="select">
                        <option value="">Select...</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $site?->project_id) == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="supervisor_id">Site Supervisor *</label>
                    <select id="supervisor_id" name="supervisor_id" class="select">
                        <option value="">Select...</option>
                        @foreach ($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" @selected(old('supervisor_id', $site?->supervisor_id) == $supervisor->id)>{{ $supervisor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="full"><label for="address">Address</label><textarea id="address" name="address" class="textarea" placeholder="Full construction site address...">{{ old('address', $site?->address) }}</textarea></div>
                <div>
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="select" required>
                        @foreach (['active', 'draft', 'inactive'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $site?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </x-admin.form-section>

            <x-admin.form-section title="Geo-Fence Settings" columns="2">
                <div><label for="latitude">Latitude *</label><input id="latitude" name="latitude" type="number" step="0.0000001" class="input" value="{{ old('latitude', $site?->latitude) }}" placeholder="24.7136"/></div>
                <div><label for="longitude">Longitude *</label><input id="longitude" name="longitude" type="number" step="0.0000001" class="input" value="{{ old('longitude', $site?->longitude) }}" placeholder="46.6753"/></div>
                <div><label for="geofence_radius">Geo-Fence Radius (meters) *</label><input id="geofence_radius" name="geofence_radius" type="number" min="10" class="input" value="{{ old('geofence_radius', $site?->geofence_radius ?? 300) }}"/></div>
                <div>
                    <label for="geofence_enabled">Geo-Fence Enabled</label>
                    <select id="geofence_enabled" name="geofence_enabled" class="select">
                        <option value="1" @selected(old('geofence_enabled', $site?->geofence_enabled ?? true))>Yes</option>
                        <option value="0" @selected(!old('geofence_enabled', $site?->geofence_enabled ?? true))>No</option>
                    </select>
                </div>
                <div>
                    <label for="attendance_inside_only">Attendance Allowed Inside Boundary</label>
                    <select id="attendance_inside_only" name="attendance_inside_only" class="select">
                        <option value="1" @selected(old('attendance_inside_only', $site?->attendance_inside_only ?? true))>Yes</option>
                        <option value="0" @selected(!old('attendance_inside_only', $site?->attendance_inside_only ?? true))>No</option>
                    </select>
                </div>
                <div>
                    <label for="offline_attendance_allowed">Offline Attendance Allowed</label>
                    <select id="offline_attendance_allowed" name="offline_attendance_allowed" class="select">
                        <option value="1" @selected(old('offline_attendance_allowed', $site?->offline_attendance_allowed ?? true))>Yes</option>
                        <option value="0" @selected(!old('offline_attendance_allowed', $site?->offline_attendance_allowed ?? true))>No</option>
                    </select>
                </div>
            </x-admin.form-section>

            <div class="form-actions">
                <a class="btn outline" href="{{ route('admin.master.sites.index') }}">Cancel</a>
                <button type="submit" class="btn primary">{{ $site ? 'Update Site' : 'Save Site' }}</button>
            </div>
        </div>

        <div>
            <div class="map-placeholder">
                Map + Geo-Fence Circle<br>
                @if ($site?->latitude)
                    {{ $site->latitude }}, {{ $site->longitude }} — radius {{ $site->geofence_radius }} m
                @else
                    Set latitude, longitude and radius to preview the geo-fence.
                @endif
            </div>
            <br/>
            <div class="note">
                The map with a circular geo-fence preview will be connected to mobile attendance check-in/check-out in the mobile app phase.
            </div>
        </div>
    </div>
</form>
