document.addEventListener('alpine:init', () => {
    Alpine.data('advancedLeafletMapComponent', (config) => ({
        map: null,
        marker: null,
        circle: null,
        currentRadius: config.radius || 500,
        routingControl: null,
        measureControl: null,
        drawControl: null,
        drawnItems: null,
        routePoints: [],
        measurePoints: [],
        showInfoPanel: false,
        currentCoordinates: '',
        totalDistance: '',
        routeInfo: '',
        isRoutingMode: false,
        isMeasureMode: false,
        isDrawingMode: false,

        async initAdvancedMap() {
            // Aguarda o carregamento das bibliotecas
            while (!window.L) await new Promise(r => setTimeout(r, 50));

            // Aguarda Leaflet Routing Machine se habilitado
            if (config.enableRouting) {
                while (!window.L.Routing) await new Promise(r => setTimeout(r, 50));
            }

            // Aguarda Leaflet Draw se habilitado  
            if (config.enableDrawing) {
                while (!window.L.Draw) await new Promise(r => setTimeout(r, 50));
            }

            // Obtém o ID do mapa do DOM ou usa um padrão
            const mapId = config.mapId || document.querySelector('[id$="-map"]')?.id;
            if (!mapId) {
                console.error('ID do mapa não encontrado');
                return;
            }

            // Inicializa o mapa
            this.map = L.map(mapId).setView([config.defaultLat, config.defaultLng], config.zoom);

            // Adiciona tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);

            // Marcador principal
            this.marker = L.marker([config.defaultLat, config.defaultLng], {
                draggable: true
            }).addTo(this.map);

            // Círculo de raio
            this.circle = L.circle([config.defaultLat, config.defaultLng], {
                radius: this.currentRadius,
                fillColor: '#3388ff',
                fillOpacity: 0.2,
                color: '#3388ff'
            }).addTo(this.map);

            // Tooltips se habilitado
            if (config.enableTooltips) {
                this.setupTooltips();
            }

            // Setup para desenho se habilitado
            if (config.enableDrawing) {
                this.setupDrawing();
            }

            // Adiciona pontos adicionais
            this.addAdditionalPoints(config.additionalPoints);

            // Event listeners
            this.setupEventListeners();

            // Livewire hooks
            this.setupLivewireHooks();

            setTimeout(() => this.map.invalidateSize(), 200);
        },

        setupTooltips() {
            this.marker.bindTooltip("Ponto de Parada Principal", {
                permanent: false,
                direction: 'top'
            });

            // Tooltip que segue o mouse
            this.map.on('mousemove', (e) => {
                const { lat, lng } = e.latlng;
                this.currentCoordinates = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                this.showInfoPanel = true;
            });

            this.map.on('mouseout', () => {
                this.showInfoPanel = false;
            });
        },

        setupDrawing() {
            this.drawnItems = new L.FeatureGroup();
            this.map.addLayer(this.drawnItems);

            this.drawControl = new L.Control.Draw({
                position: 'topright',
                draw: {
                    polyline: true,
                    polygon: true,
                    circle: true,
                    rectangle: true,
                    marker: true
                },
                edit: {
                    featureGroup: this.drawnItems,
                    remove: true
                }
            });

            this.map.on(L.Draw.Event.CREATED, (e) => {
                const layer = e.layer;
                this.drawnItems.addLayer(layer);

                if (config.enableTooltips) {
                    layer.bindTooltip(`Tipo: ${e.layerType}`, {
                        permanent: false
                    });
                }
            });
        },

        setupEventListeners() {
            const updateMainMarker = (lat, lng) => {
                this.marker.setLatLng([lat, lng]);
                this.circle.setLatLng([lat, lng]);
                this.circle.setRadius(this.currentRadius);

                // Notifica o Livewire
                if (this.$wire && this.$wire.mapClicked) {
                    this.$wire.mapClicked({ lat, lng });
                }
            };

            this.map.on('click', (e) => {
                if (this.isRoutingMode) {
                    this.addRoutePoint(e.latlng);
                } else if (this.isMeasureMode) {
                    this.addMeasurePoint(e.latlng);
                } else {
                    const { lat, lng } = e.latlng;
                    updateMainMarker(lat, lng);
                }
            });

            this.marker.on('dragend', (e) => {
                const { lat, lng } = e.target.getLatLng();
                updateMainMarker(lat, lng);
            });
        },

        setupLivewireHooks() {
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('message.processed', (message, component) => {
                    const newRadius = parseInt(component.get('data.raio')) || 2000;
                    if (newRadius !== this.currentRadius) {
                        this.currentRadius = newRadius;
                        this.circle.setRadius(this.currentRadius);
                    }
                });

                Livewire.on('raioUpdated', ({ radius }) => {
                    if (this.circle) {
                        this.currentRadius = parseInt(radius);
                        this.circle.setRadius(this.currentRadius);
                    }
                });
            }
        },

        addAdditionalPoints(points) {
            if (!points || points.length === 0) return;

            points.forEach((point, index) => {
                const marker = L.marker([point.lat, point.lng]).addTo(this.map);

                if (config.enableTooltips && point.name) {
                    marker.bindTooltip(point.name, {
                        permanent: false
                    });
                }

                if (point.popup) {
                    marker.bindPopup(point.popup);
                }
            });
        },

        toggleRouting() {
            this.isRoutingMode = !this.isRoutingMode;
            this.isMeasureMode = false;

            if (this.isRoutingMode) {
                this.routePoints = [];
                this.map.getContainer().style.cursor = 'crosshair';
            } else {
                this.clearRouting();
                this.map.getContainer().style.cursor = '';
            }
        },

        addRoutePoint(latlng) {
            this.routePoints.push(latlng);

            // Adiciona marcador temporário
            L.marker([latlng.lat, latlng.lng])
                .addTo(this.map)
                .bindTooltip(`Ponto ${this.routePoints.length}`, {
                    permanent: true
                });

            if (this.routePoints.length >= 2) {
                this.calculateRoute();
            }
        },

        calculateRoute() {
            if (this.routingControl) {
                this.map.removeControl(this.routingControl);
            }

            this.routingControl = L.Routing.control({
                waypoints: this.routePoints,
                routeWhileDragging: true,
                addWaypoints: true,
                createMarker: function() { return null; }, // Remove marcadores padrão
                lineOptions: {
                    styles: [{
                        color: '#ff6600',
                        weight: 4,
                        opacity: 0.8
                    }]
                }
            }).addTo(this.map);

            this.routingControl.on('routesfound', (e) => {
                const routes = e.routes;
                const summary = routes[0].summary;
                this.totalDistance = `${(summary.totalDistance / 1000).toFixed(2)} km`;
                this.routeInfo = `${Math.round(summary.totalTime / 60)} min`;
            });
        },

        clearRouting() {
            if (this.routingControl) {
                this.map.removeControl(this.routingControl);
                this.routingControl = null;
            }
            this.routePoints = [];
            this.totalDistance = '';
            this.routeInfo = '';
        },

        toggleDistanceTool() {
            this.isMeasureMode = !this.isMeasureMode;
            this.isRoutingMode = false;

            if (this.isMeasureMode) {
                this.measurePoints = [];
                this.map.getContainer().style.cursor = 'crosshair';
            } else {
                this.clearMeasurement();
                this.map.getContainer().style.cursor = '';
            }
        },

        addMeasurePoint(latlng) {
            this.measurePoints.push(latlng);

            L.marker([latlng.lat, latlng.lng], {
                icon: L.divIcon({
                    className: 'measure-marker',
                    html: `<div style="background: red; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px;">${this.measurePoints.length}</div>`,
                    iconSize: [20, 20]
                })
            }).addTo(this.map);

            if (this.measurePoints.length >= 2) {
                this.calculateDistance();
            }
        },

        calculateDistance() {
            let totalDistance = 0;

            for (let i = 1; i < this.measurePoints.length; i++) {
                const distance = this.measurePoints[i - 1].distanceTo(this.measurePoints[i]);
                totalDistance += distance;

                // Linha entre pontos
                L.polyline([this.measurePoints[i - 1], this.measurePoints[i]], {
                    color: 'red',
                    weight: 3,
                    dashArray: '5, 10'
                }).addTo(this.map);
            }

            this.totalDistance = `${(totalDistance / 1000).toFixed(3)} km`;
        },

        clearMeasurement() {
            this.measurePoints = [];
            this.totalDistance = '';
            // Remove marcadores e linhas de medição
            this.map.eachLayer((layer) => {
                if (layer instanceof L.Marker && layer.options.icon && layer.options.icon.options.className === 'measure-marker') {
                    this.map.removeLayer(layer);
                }
                if (layer instanceof L.Polyline && layer.options.dashArray) {
                    this.map.removeLayer(layer);
                }
            });
        },

        toggleDrawing() {
            this.isDrawingMode = !this.isDrawingMode;

            if (this.isDrawingMode && this.drawControl) {
                this.map.addControl(this.drawControl);
            } else if (this.drawControl) {
                this.map.removeControl(this.drawControl);
            }
        },

        clearMap() {
            // Limpa rotas
            this.clearRouting();

            // Limpa medições
            this.clearMeasurement();

            // Limpa desenhos
            if (this.drawnItems) {
                this.drawnItems.clearLayers();
            }

            // Remove controles
            if (this.drawControl && this.map.hasLayer(this.drawControl)) {
                this.map.removeControl(this.drawControl);
            }

            // Reset estados
            this.isRoutingMode = false;
            this.isMeasureMode = false;
            this.isDrawingMode = false;
            this.map.getContainer().style.cursor = '';
            this.showInfoPanel = false;
        }
    }));
});