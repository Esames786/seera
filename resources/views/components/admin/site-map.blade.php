@props([
    'lat' => null,
    'lng' => null,
    'radius' => 300,
    'editable' => false,        // click / drag to set the pin and write the inputs below
    'latInput' => 'latitude',
    'lngInput' => 'longitude',
    'radiusInput' => 'geofence_radius',
    'height' => '380px',
])

{{--
    Real map for a site (client change request CR-16): OpenStreetMap tiles via
    Leaflet, the saved coordinates as a pin and the geo-fence radius as a circle.
    In editable mode a click or drag moves the pin and fills the coordinate inputs.
--}}
<div class="site-map-wrap">
    <div id="site-map" class="site-map" style="height: {{ $height }}"
         data-lat="{{ $lat }}" data-lng="{{ $lng }}" data-radius="{{ $radius }}"
         data-editable="{{ $editable ? 1 : 0 }}"
         data-lat-input="{{ $latInput }}" data-lng-input="{{ $lngInput }}" data-radius-input="{{ $radiusInput }}">
        <div class="site-map-fallback">
            @if ($lat && $lng)
                {{ $lat }}, {{ $lng }} — radius {{ $radius }} m
            @else
                No coordinates saved yet.
            @endif
        </div>
    </div>
    <div class="small site-map-note" id="site-map-note">
        @if ($editable)
            Click the map to place the site pin, or drag the pin. The circle is the geo-fence radius.
        @elseif ($lat && $lng)
            Pin: {{ $lat }}, {{ $lng }} · geo-fence radius {{ $radius }} m
        @else
            Add latitude and longitude to the site to see it on the map.
        @endif
    </div>
</div>

@once
    @push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
    @endpush
    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
        window.seeraSiteMap = function (el) {
            if (!el) return;
            var fallback = el.querySelector('.site-map-fallback');
            if (typeof L === 'undefined') {
                if (fallback) fallback.textContent = 'The map could not be loaded. ' + fallback.textContent;
                return;
            }

            var note = document.getElementById('site-map-note');
            var editable = el.dataset.editable === '1';
            var latInput = document.getElementById(el.dataset.latInput);
            var lngInput = document.getElementById(el.dataset.lngInput);
            var radiusInput = document.getElementById(el.dataset.radiusInput);

            function num(value) { var n = parseFloat(value); return isNaN(n) ? null : n; }
            function radius() {
                var r = radiusInput ? num(radiusInput.value) : null;
                if (r === null) r = num(el.dataset.radius);
                return r && r > 0 ? r : 300;
            }

            var lat = num(el.dataset.lat), lng = num(el.dataset.lng);
            var hasPin = lat !== null && lng !== null;
            if (fallback) fallback.remove();

            // Riyadh when nothing is saved yet; the pin decides the view otherwise.
            var map = L.map(el, { scrollWheelZoom: editable }).setView(hasPin ? [lat, lng] : [24.7136, 46.6753], hasPin ? 16 : 5);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            var marker = null, circle = null;

            function place(point, fit) {
                if (!marker) {
                    marker = L.marker(point, { draggable: editable }).addTo(map);
                    circle = L.circle(point, { radius: radius(), color: '#0d7ff2', fillColor: '#0d7ff2', fillOpacity: 0.12, weight: 2 }).addTo(map);
                    if (editable) marker.on('dragend', function () { set(marker.getLatLng()); });
                } else {
                    marker.setLatLng(point);
                    circle.setLatLng(point);
                    circle.setRadius(radius());
                }
                if (fit) map.fitBounds(circle.getBounds(), { padding: [24, 24] });
                if (note) {
                    var p = marker.getLatLng();
                    note.textContent = 'Pin: ' + p.lat.toFixed(7) + ', ' + p.lng.toFixed(7) + ' · geo-fence radius ' + radius() + ' m' + (editable ? ' · click or drag to move' : '');
                }
            }

            function set(point) {
                place(point, false);
                if (latInput) latInput.value = point.lat.toFixed(7);
                if (lngInput) lngInput.value = point.lng.toFixed(7);
            }

            if (hasPin) place([lat, lng], true);

            if (editable) {
                map.on('click', function (event) { set(event.latlng); });
                function fromInputs() {
                    var a = latInput ? num(latInput.value) : null, b = lngInput ? num(lngInput.value) : null;
                    if (a === null || b === null) return;
                    place([a, b], !marker);
                    if (!map.getBounds().contains([a, b])) map.panTo([a, b]);
                }
                if (latInput) latInput.addEventListener('change', fromInputs);
                if (lngInput) lngInput.addEventListener('change', fromInputs);
                if (radiusInput) radiusInput.addEventListener('input', function () { if (marker) place(marker.getLatLng(), false); });
            }
        };
    </script>
    @endpush
@endonce

@push('scripts')
<script>window.seeraSiteMap(document.getElementById('site-map'));</script>
@endpush
