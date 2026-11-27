/* =========================================================
   خرائط وجاهة — Leaflet + OpenStreetMap
   ========================================================= */
(function () {
    const SYRIA_CENTER = [34.8, 38.5];
    const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

    // دبوس بألوان وجاهة
    function pinIcon(color) {
        return L.divIcon({
            className: 'wj-pin-wrap',
            html: `<svg class="wj-pin" viewBox="0 0 32 44" width="32" height="44">
                     <path d="M16 0C7.2 0 0 7.2 0 16c0 11.6 16 28 16 28s16-16.4 16-28C32 7.2 24.8 0 16 0z" fill="${color}"/>
                     <circle cx="16" cy="16" r="6.5" fill="#fff"/>
                   </svg>`,
            iconSize: [32, 44],
            iconAnchor: [16, 44],
            popupAnchor: [0, -40],
        });
    }

    function baseMap(el, center, zoom) {
        const map = L.map(el, { scrollWheelZoom: true }).setView(center, zoom);
        L.tileLayer(TILE_URL, { attribution: ATTRIBUTION, maxZoom: 19 }).addTo(map);
        // إصلاح الحجم عند فتح أقسام مطوية
        setTimeout(() => map.invalidateSize(), 300);
        new ResizeObserver(() => map.invalidateSize()).observe(el);
        return map;
    }

    /* ---------- محدّد الموقع في نموذج العقار ---------- */
    window.wajahaMapPicker = (config) => ({
        lat: config.lat,
        lng: config.lng,
        map: null,
        marker: null,
        query: '',
        results: [],
        searching: false,

        init() {
            const has = this.lat && this.lng;
            this.map = baseMap(this.$refs.map, has ? [this.lat, this.lng] : SYRIA_CENTER, has ? 16 : 7);
            if (has) this.placeMarker(this.lat, this.lng);

            this.map.on('click', (e) => this.setPoint(e.latlng.lat, e.latlng.lng));

            // تحديث الدبوس عند الكتابة اليدوية للإحداثيات
            this.$watch('lat', () => this.syncFromInputs());
            this.$watch('lng', () => this.syncFromInputs());
        },

        syncFromInputs() {
            const lat = parseFloat(this.lat), lng = parseFloat(this.lng);
            if (isNaN(lat) || isNaN(lng)) return;
            if (this.marker) {
                const cur = this.marker.getLatLng();
                if (Math.abs(cur.lat - lat) < 1e-7 && Math.abs(cur.lng - lng) < 1e-7) return;
            }
            this.placeMarker(lat, lng);
        },

        setPoint(lat, lng) {
            this.lat = Number(lat.toFixed(7));
            this.lng = Number(lng.toFixed(7));
            this.placeMarker(this.lat, this.lng);
        },

        placeMarker(lat, lng) {
            if (!this.marker) {
                this.marker = L.marker([lat, lng], { draggable: true, icon: pinIcon('#043c36') }).addTo(this.map);
                this.marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    this.setPoint(p.lat, p.lng);
                });
            } else {
                this.marker.setLatLng([lat, lng]);
            }
        },

        async search() {
            if (!this.query.trim()) return;
            this.searching = true;
            try {
                const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&accept-language=ar&countrycodes=sy&q='
                    + encodeURIComponent(this.query);
                this.results = await (await fetch(url)).json();
            } catch (e) {
                this.results = [];
            }
            this.searching = false;
        },

        choose(r) {
            this.setPoint(parseFloat(r.lat), parseFloat(r.lon));
            this.map.setView([this.lat, this.lng], 16);
            this.results = [];
        },

        locateMe() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition((pos) => {
                this.setPoint(pos.coords.latitude, pos.coords.longitude);
                this.map.setView([this.lat, this.lng], 17);
            });
        },

        clear() {
            this.lat = null;
            this.lng = null;
            if (this.marker) {
                this.map.removeLayer(this.marker);
                this.marker = null;
            }
        },
    });

    /* ---------- صفحة خريطة العقارات ---------- */
    window.wajahaPropertiesMap = (config) => ({
        map: null,
        cluster: null,
        markers: {},

        init() {
            this.map = baseMap(this.$refs.map, SYRIA_CENTER, 7);
            this.cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 });
            this.map.addLayer(this.cluster);

            this.render(this.$wire.markers, config.focus);
            this.$wire.$watch('markers', (value) => this.render(value, null));
        },

        render(items, focusId) {
            this.cluster.clearLayers();
            this.markers = {};

            (items || []).forEach((p) => {
                const m = L.marker([p.lat, p.lng], { icon: pinIcon(p.available ? '#16a34a' : '#dc2626') })
                    .bindPopup(p.popup, { maxWidth: 280, minWidth: 240, className: 'wj-popup' });
                this.markers[p.id] = m;
                this.cluster.addLayer(m);
            });

            if (focusId && this.markers[focusId]) {
                const m = this.markers[focusId];
                this.cluster.zoomToShowLayer(m, () => m.openPopup());
            } else if (items && items.length) {
                this.map.fitBounds(this.cluster.getBounds(), { padding: [40, 40], maxZoom: 15 });
            }
        },
    });
})();
