<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Rota Escolar em Umuarama</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }

        h1 {
            text-align: center;
            padding: 15px;
            margin: 0 0 20px 0;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 300px 1fr 350px;
            gap: 20px;
        }

        .sidebar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .info-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        #map {
            height: 600px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .info-item {
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
        }

        .info-item label {
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-size: 1.1em;
            color: #666;
        }

        .fuel-item {
            border-left-color: #FF9800;
        }

        input[type="time"],
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }

        button:hover {
            background: #1976D2;
        }

        .add-point {
            background: #4CAF50;
        }

        .add-point:hover {
            background: #45a049;
        }

        h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #2196F3;
            padding-bottom: 10px;
        }

        .point-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .point-item {
            padding: 8px;
            margin: 5px 0;
            background: #f0f0f0;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .remove-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            width: auto;
            margin: 0;
        }

        .remove-btn:hover {
            background: #d32f2f;
        }

        .mode-selector {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }

        .mode-btn {
            flex: 1;
            min-width: 80px;
            padding: 10px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }

        .mode-btn:hover {
            border-color: #2196F3;
            background: #f0f8ff;
        }

        .mode-btn.active {
            border-color: #2196F3;
            background: #2196F3;
            color: white;
            font-weight: bold;
        }

        .mode-btn.active-parada {
            border-color: #2196F3;
            background: #2196F3;
        }

        .mode-btn.active-escola {
            border-color: #F44336;
            background: #F44336;
        }

        .mode-btn.active-aluno {
            border-color: #4CAF50;
            background: #4CAF50;
        }

        .status-msg {
            padding: 10px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
            display: none;
        }

        .status-msg.show {
            display: block;
        }

        .highlight-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #FF9800;
        }
    </style>
</head>

<body>
    <h1>Teste Rota 1 - Sistema de Rotas Escolares</h1>

    <div class="container">
        <div class="sidebar">
            <h3>Modo de Adição</h3>
            <div class="status-msg" id="statusMsg">Clique no mapa para adicionar o ponto</div>

            <div class="mode-selector">
                <div class="mode-btn" onclick="setMode('parada')" id="btn-parada">
                    🚏 Parada
                </div>
                <div class="mode-btn" onclick="setMode('escola')" id="btn-escola">
                    🏫 Escola
                </div>
                <div class="mode-btn" onclick="setMode('aluno')" id="btn-aluno">
                    👨‍🎓 Aluno
                </div>
            </div>

            <button onclick="desativarModo()">Desativar Modo de Adição</button>

            <h3>Pontos na Rota</h3>
            <div class="point-list" id="pointList"></div>

            <button onclick="recalcularRota()">Recalcular Rota</button>

            <div style="margin-top: 20px;">
                <h3>Horário de Saída</h3>
                <label>Hora de partida:</label>
                <input type="time" id="departureTime" value="07:00" onchange="calcularHorarios()">
            </div>

            <div style="margin-top: 20px;">
                <h3>Consumo do Veículo</h3>
                <label>Km por Litro:</label>
                <input type="number" id="fuelConsumption" value="10" min="1" max="50" step="0.1" onchange="calcularCombustivel()">
                <small style="color: #666; display: block; margin-top: 5px;">Ex: 10 km/L</small>
                
                <label style="margin-top: 15px;">Preço do Combustível (R$):</label>
                <input type="number" id="fuelPrice" value="5.50" min="0" step="0.01" onchange="calcularCombustivel()">
            </div>
        </div>

        <div>
            <div id="map"></div>
        </div>

        <div class="info-panel">
            <h3>Informações da Rota</h3>
            <div class="info-item">
                <label>Distância Total:</label>
                <div class="value" id="distanciaTotal">Calculando...</div>
            </div>
            <div class="info-item">
                <label>Tempo Estimado:</label>
                <div class="value" id="tempoEstimado">Calculando...</div>
            </div>
            <div class="info-item">
                <label>Horário de Saída:</label>
                <div class="value" id="horarioSaida">07:00</div>
            </div>
            <div class="info-item">
                <label>Horário de Chegada:</label>
                <div class="value" id="horarioChegada">Calculando...</div>
            </div>
            <div class="info-item">
                <label>Número de Paradas:</label>
                <div class="value" id="numParadas">6</div>
            </div>
            <div class="info-item">
                <label>Número de Alunos:</label>
                <div class="value" id="numAlunos">18</div>
            </div>

            <h3 style="margin-top: 25px; border-bottom-color: #FF9800;">Custo de Combustível</h3>
            <div class="info-item fuel-item">
                <label>Litros Necessários:</label>
                <div class="value" id="litrosNecessarios">Calculando...</div>
            </div>
            <div class="info-item fuel-item">
                <label>Custo da Rota:</label>
                <div class="value highlight-value" id="custoRota">Calculando...</div>
            </div>
            <div class="info-item fuel-item">
                <label>Consumo Médio:</label>
                <div class="value" id="consumoMedio">10.0 km/L</div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script>
        const map = L.map('map').setView([-23.7666, -53.3121], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const icons = {
            parada: L.icon({
                iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxNiIgY3k9IjE2IiByPSIxNCIgZmlsbD0iIzIxOTZGMyIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIi8+PHRleHQgeD0iMTYiIHk9IjIxIiBmb250LXNpemU9IjE2IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC13ZWlnaHQ9ImJvbGQiPlA8L3RleHQ+PC9zdmc+',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            }),
            escola: L.icon({
                iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxNiIgY3k9IjE2IiByPSIxNCIgZmlsbD0iI0Y0NDMzNiIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIi8+PHRleHQgeD0iMTYiIHk9IjIxIiBmb250LXNpemU9IjE2IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC13ZWlnaHQ9ImJvbGQiPkU8L3RleHQ+PC9zdmc+',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            }),
            aluno: L.icon({
                iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIgZmlsbD0iIzRDQUY1MCIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIi8+PHRleHQgeD0iMTIiIHk9IjE2IiBmb250LXNpemU9IjEyIiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC13ZWlnaHQ9ImJvbGQiPkE8L3RleHQ+PC9zdmc+',
                iconSize: [24, 24],
                iconAnchor: [12, 24],
                popupAnchor: [0, -24]
            })
        };

        let pontosPrincipais = [{
                lat: -23.756000,
                lng: -53.325000,
                type: 'parada',
                label: 'Ponto A - Norte Oeste'
            },
            {
                lat: -23.758500,
                lng: -53.305000,
                type: 'parada',
                label: 'Ponto B - Norte Leste'
            },
            {
                lat: -23.768000,
                lng: -53.300000,
                type: 'parada',
                label: 'Ponto C - Leste'
            },
            {
                lat: -23.778000,
                lng: -53.310000,
                type: 'parada',
                label: 'Ponto D - Sul Leste'
            },
            {
                lat: -23.775000,
                lng: -53.325000,
                type: 'parada',
                label: 'Ponto E - Sul'
            },
            {
                lat: -23.762000,
                lng: -53.328000,
                type: 'parada',
                label: 'Ponto F - Oeste'
            },
            {
                lat: -23.766900,
                lng: -53.312000,
                type: 'escola',
                label: 'Escola Central'
            },
            {
                lat: -23.770000,
                lng: -53.320000,
                type: 'escola',
                label: 'Escola Municipal'
            }
        ];

        let alunos = [];
        let markers = [];
        let circles = [];
        let routingControl = null;
        let routeInfo = {
            distance: 0,
            time: 0
        };
        let addMode = null;
        let pontoCounter = {
            parada: 7,
            escola: 3,
            aluno: 1
        };

        map.on('click', function(e) {
            if (addMode) {
                adicionarPontoNoMapa(e.latlng.lat, e.latlng.lng);
            }
        });

        function setMode(mode) {
            addMode = mode;
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.classList.remove('active', 'active-parada', 'active-escola', 'active-aluno');
            });
            const btn = document.getElementById(`btn-${mode}`);
            btn.classList.add('active', `active-${mode}`);
            const statusMsg = document.getElementById('statusMsg');
            statusMsg.textContent = `Clique no mapa para adicionar ${mode === 'parada' ? 'uma parada' : mode === 'escola' ? 'uma escola' : 'um aluno'}`;
            statusMsg.classList.add('show');
            document.getElementById('map').style.cursor = 'crosshair';
        }

        function desativarModo() {
            addMode = null;
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.classList.remove('active', 'active-parada', 'active-escola', 'active-aluno');
            });
            document.getElementById('statusMsg').classList.remove('show');
            document.getElementById('map').style.cursor = '';
        }

        function adicionarPontoNoMapa(lat, lng) {
            if (!addMode) return;

            let label = '';
            if (addMode === 'parada') {
                label = `Ponto ${String.fromCharCode(64 + pontoCounter.parada)}`;
                pontoCounter.parada++;
            } else if (addMode === 'escola') {
                label = `Escola ${pontoCounter.escola}`;
                pontoCounter.escola++;
            } else if (addMode === 'aluno') {
                label = `Aluno ${pontoCounter.aluno}`;
                pontoCounter.aluno++;
            }

            pontosPrincipais.push({
                lat: lat,
                lng: lng,
                type: addMode,
                label: label
            });

            desenharMapa();
            const statusMsg = document.getElementById('statusMsg');
            statusMsg.textContent = `${label} adicionado! Clique novamente para adicionar mais.`;
        }

        function gerarAlunos() {
            alunos = [];
            pontosPrincipais.forEach(p => {
                if (p.type === 'parada') {
                    alunos.push({
                        lat: p.lat + 0.0012,
                        lng: p.lng + 0.0008,
                        type: 'aluno',
                        label: `Aluno 1 - ${p.label}`
                    }, {
                        lat: p.lat - 0.0010,
                        lng: p.lng - 0.0006,
                        type: 'aluno',
                        label: `Aluno 2 - ${p.label}`
                    }, {
                        lat: p.lat + 0.0008,
                        lng: p.lng - 0.0010,
                        type: 'aluno',
                        label: `Aluno 3 - ${p.label}`
                    });
                }
            });
        }

        function limparMapa() {
            markers.forEach(m => map.removeLayer(m));
            circles.forEach(c => map.removeLayer(c));
            markers = [];
            circles = [];
            if (routingControl) {
                map.removeControl(routingControl);
                routingControl = null;
            }
        }

        function desenharMapa() {
            limparMapa();
            gerarAlunos();

            const stops = [...pontosPrincipais, ...alunos];

            pontosPrincipais.forEach(p => {
                let circle;
                if (p.type === 'parada') {
                    circle = L.circle([p.lat, p.lng], {
                        color: '#2196F3',
                        fillColor: '#2196F3',
                        fillOpacity: 0.15,
                        radius: 500,
                        weight: 2
                    }).addTo(map);
                } else if (p.type === 'escola') {
                    circle = L.circle([p.lat, p.lng], {
                        color: '#F44336',
                        fillColor: '#F44336',
                        fillOpacity: 0.1,
                        radius: 2000,
                        weight: 2
                    }).addTo(map);
                }
                if (circle) circles.push(circle);
            });

            stops.forEach(stop => {
                const marker = L.marker([stop.lat, stop.lng], {
                        icon: icons[stop.type]
                    })
                    .addTo(map)
                    .bindPopup(`<b>${stop.label}</b><br>Lat: ${stop.lat.toFixed(6)}<br>Lng: ${stop.lng.toFixed(6)}`);
                markers.push(marker);
            });

            const waypoints = pontosPrincipais
                .filter(p => p.type === 'parada' || p.type === 'escola')
                .map(p => L.latLng(p.lat, p.lng));

            routingControl = L.Routing.control({
                waypoints: waypoints,
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                }),
                lineOptions: {
                    styles: [{
                        color: '#ff0707ff',
                        opacity: 0.8,
                        weight: 5
                    }]
                },
                show: false,
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                createMarker: () => null
            }).addTo(map);

            routingControl.on('routesfound', function(e) {
                const routes = e.routes;
                console.log(routes);
                const summary = routes[0].summary;
                routeInfo.distance = (summary.totalDistance / 1000).toFixed(2);
                routeInfo.time = Math.round(summary.totalTime / 60);
                atualizarInformacoes();
            });

            atualizarListaPontos();
            atualizarContadores();
        }

        function atualizarInformacoes() {
            document.getElementById('distanciaTotal').textContent = `${routeInfo.distance} km`;
            document.getElementById('tempoEstimado').textContent = `${routeInfo.time} minutos`;
            calcularHorarios();
            calcularCombustivel();
        }

        function calcularHorarios() {
            const horarioSaida = document.getElementById('departureTime').value;
            document.getElementById('horarioSaida').textContent = horarioSaida;

            if (routeInfo.time > 0) {
                const [horas, minutos] = horarioSaida.split(':').map(Number);
                const totalMinutos = horas * 60 + minutos + routeInfo.time;
                const horasChegada = Math.floor(totalMinutos / 60) % 24;
                const minutosChegada = totalMinutos % 60;
                const horarioChegada = `${String(horasChegada).padStart(2, '0')}:${String(minutosChegada).padStart(2, '0')}`;
                document.getElementById('horarioChegada').textContent = horarioChegada;
            }
        }

        function calcularCombustivel() {
            const kmPorLitro = parseFloat(document.getElementById('fuelConsumption').value) || 10;
            const precoCombustivel = parseFloat(document.getElementById('fuelPrice').value) || 5.50;
            const distancia = parseFloat(routeInfo.distance) || 0;

            if (distancia > 0) {
                const litrosNecessarios = distancia / kmPorLitro;
                const custoTotal = litrosNecessarios * precoCombustivel;

                document.getElementById('litrosNecessarios').textContent = `${litrosNecessarios.toFixed(2)} L`;
                document.getElementById('custoRota').textContent = `R$ ${custoTotal.toFixed(2)}`;
                document.getElementById('consumoMedio').textContent = `${kmPorLitro.toFixed(1)} km/L`;
            } else {
                document.getElementById('litrosNecessarios').textContent = 'Calculando...';
                document.getElementById('custoRota').textContent = 'Calculando...';
                document.getElementById('consumoMedio').textContent = `${kmPorLitro.toFixed(1)} km/L`;
            }
        }

        function atualizarContadores() {
            const numParadas = pontosPrincipais.filter(p => p.type === 'parada').length;
            const numAlunos = alunos.length;
            document.getElementById('numParadas').textContent = numParadas;
            document.getElementById('numAlunos').textContent = numAlunos;
        }

        function atualizarListaPontos() {
            const lista = document.getElementById('pointList');
            lista.innerHTML = '';
            pontosPrincipais.forEach((p, index) => {
                const div = document.createElement('div');
                div.className = 'point-item';
                div.innerHTML = `
                    <span>${p.label} (${p.type})</span>
                    <button class="remove-btn" onclick="removerPonto(${index})">Remover</button>
                `;
                lista.appendChild(div);
            });
        }

        function removerPonto(index) {
            pontosPrincipais.splice(index, 1);
            desenharMapa();
        }

        function recalcularRota() {
            desenharMapa();
        }

        desenharMapa();
    </script>
</body>

</html>