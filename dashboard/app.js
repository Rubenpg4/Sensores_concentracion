$(document).ready(function () {
    // --- Referencias DOM Histórico ---
    const $chartContainer = $("#concentration-chart");
    const $loadingOverlay = $("#loading-overlay");

    // --- Referencias DOM Tiempo Real ---
    const $micChart = $("#mic-chart");
    const $gyroChart = $("#gyro-chart");
    const $gravityChart = $("#gravity-chart");
    const $accelChart = $("#accel-chart");

    // KPIs
    const $kpiEstado = $("#kpi-estado");
    const $kpiRuido = $("#kpi-ruido");
    const $kpiVarianza = $("#kpi-varianza");
    const $kpiBrillo = $("#kpi-brillo");

    // --- Configuraciones Base de Flot ---
    const baseOptions = {
        series: {
            lines: { show: true, lineWidth: 2, fill: true },
            shadowSize: 0
        },
        xaxis: { mode: "time", timezone: "browser", timeformat: "%H:%M:%S", tickSize: [10, "second"], font: { size: 9, color: "#94a3b8", family: "Inter" } },
        yaxis: { tickColor: "#334155", font: { size: 10, color: "#94a3b8", family: "Inter" } },
        grid: { borderWidth: 0, margin: { top: 10, left: 10, right: 10, bottom: 10 } }
    };

    // Crear copias personalizadas de configuración para cada gráfica con colores distintos
    const micOptions = $.extend(true, {}, baseOptions, {
        colors: ["#a855f7"], // Purple
        series: { lines: { fillColor: { colors: [{ opacity: 0.1 }, { opacity: 0.3 }] } } }
    });

    const gyroOptions = $.extend(true, {}, baseOptions, {
        colors: ["#f59e0b"], // Amber
        series: { lines: { fillColor: { colors: [{ opacity: 0.1 }, { opacity: 0.3 }] } } }
    });

    const gravityOptions = $.extend(true, {}, baseOptions, {
        colors: ["#0ea5e9"], // Sky blue
        series: { lines: { fillColor: { colors: [{ opacity: 0.1 }, { opacity: 0.3 }] } } }
    });

    const accelOptions = $.extend(true, {}, baseOptions, {
        colors: ["#ef4444"], // Red
        series: { lines: { fillColor: { colors: [{ opacity: 0.1 }, { opacity: 0.3 }] } } }
    });

    // Opciones del histórico (Barras apiladas por hora)
    const dailyOptions = {
        series: {
            stack: true,
            bars: {
                show: true,
                barWidth: 3600000 * 0.7,
                align: "center",
                lineWidth: 1,
                fill: 0.85
            }
        },
        xaxis: {
            mode: "time",
            timezone: "browser",
            timeformat: "%d/%m %H:00",
            tickSize: [1, "hour"],
            font: { color: "#94a3b8", family: "Inter" }
        },
        yaxis: {
            min: 0,
            max: 60,
            tickDecimals: 0,
            tickFormatter: function(v) { return v + " min"; },
            tickColor: "#334155",
            font: { color: "#94a3b8", family: "Inter" }
        },
        grid: { borderWidth: 0, hoverable: true, mouseActiveRadius: 20, margin: { top: 20, left: 10, right: 20, bottom: 20 } },
        legend: {
            show: true,
            position: "nw",
            backgroundColor: "rgba(15,23,42,0.7)",
            labelBoxBorderColor: "transparent"
        }
    };

    // Variables para mantener las gráficas en vivo
    let plotMic, plotGyro, plotGravity, plotAccel;
    let plotHistorico = null, historicDataClone = null;

    // --- Funciones de Carga ---

    function renderHistoricStats(data) {
        var conc = data[0] || { data: [] };
        var dist = data[1] || { data: [] };

        var totalConc = conc.data.reduce(function(s, p) { return s + p[1]; }, 0);
        var totalDist = dist.data.reduce(function(s, p) { return s + p[1]; }, 0);
        var total = totalConc + totalDist;
        var pct = total > 0 ? Math.round(totalConc / total * 100) : 0;

        var allTs = conc.data.concat(dist.data).map(function(p) { return p[0]; });
        var minTs = Math.min.apply(null, allTs) - 1800000;
        var maxTs = Math.max.apply(null, allTs) + 1800000;

        function fmt(ms) {
            var d = new Date(ms);
            return ("0" + d.getDate()).slice(-2) + "/" + ("0" + (d.getMonth() + 1)).slice(-2) +
                   " " + ("0" + d.getHours()).slice(-2) + ":00";
        }

        var html =
            '<div class="stat-item"><span class="stat-label">Concentrado</span>' +
            '<span class="stat-value conc-val">' + Math.round(totalConc) + ' min</span></div>' +
            '<div class="stat-sep"></div>' +
            '<div class="stat-item"><span class="stat-label">Distraído</span>' +
            '<span class="stat-value dist-val">' + Math.round(totalDist) + ' min</span></div>' +
            '<div class="stat-sep"></div>' +
            '<div class="stat-item"><span class="stat-label">Eficiencia</span>' +
            '<span class="stat-value">' + pct + '%</span></div>' +
            '<div class="stat-sep"></div>' +
            '<div class="stat-item"><span class="stat-label">Período registrado</span>' +
            '<span class="stat-value stat-period">' + fmt(minTs) + ' – ' + fmt(maxTs) + '</span></div>';

        $('#chart-stats').html(html);
    }

    function fetchHistoricalData() {
        $loadingOverlay.addClass('active');
        $.ajax({
            url: "../api/analytics.php",
            method: "GET",
            dataType: "json",
            success: function (data) {
                if (!data || !data[0] || !data[0].data || (data[0].data.length === 0 && data[1].data.length === 0)) {
                    $chartContainer.html("<div class='empty-state'>Aún no se han sincronizado datos de los sensores.</div>");
                    return;
                }
                // Clonar antes de que el plugin stack mute los valores
                historicDataClone = data.map(function(s) {
                    return { label: s.label, color: s.color, data: s.data.map(function(p) { return [p[0], p[1]]; }) };
                });
                renderHistoricStats(historicDataClone);
                plotHistorico = $.plot($chartContainer, data, dailyOptions);
            },
            complete: function () { setTimeout(() => $loadingOverlay.removeClass('active'), 300); }
        });
    }

    function updatePlot(plotObj, container, data, options) {
        if (!data || data.length === 0) {
            container.html("<div class='empty-state'>Esperando datos...</div>");
            return null;
        }
        if (!plotObj && container.html().includes('Esperando')) {
            container.empty();
        }
        if (!plotObj) {
            return $.plot(container, [data], options);
        } else {
            plotObj.setData([data]);
            plotObj.setupGrid();
            plotObj.draw();
            return plotObj;
        }
    }

    function fetchRealtimeData() {
        $.ajax({
            url: "../api/api_realtime.php",
            method: "GET",
            dataType: "json",
            success: function (data) {
                // Actualizar KPIs
                $kpiRuido.text(data.latest_mic + " dB");
                $kpiVarianza.text(data.variance);
                $kpiBrillo.text(data.latest_bright);

                // Estado Visual
                if (data.estado === "CONCENTRADO") {
                    $kpiEstado.text("CONCENTRADO").removeClass("state-distracted state-waiting").addClass("state-concentrated");
                } else if (data.estado === "DISTRAÍDO") {
                    $kpiEstado.text("DISTRAÍDO").removeClass("state-concentrated state-waiting").addClass("state-distracted");
                }

                // Actualizar las 4 mini-gráficas
                plotMic = updatePlot(plotMic, $micChart, data.mic_history, micOptions);
                plotGyro = updatePlot(plotGyro, $gyroChart, data.gyro_history, gyroOptions);
                plotGravity = updatePlot(plotGravity, $gravityChart, data.gravity_history, gravityOptions);
                plotAccel = updatePlot(plotAccel, $accelChart, data.accel_history, accelOptions);
            }
        });
    }

    // Tooltip de la gráfica histórica
    var $tooltip = $('<div class="flot-tooltip"></div>').appendTo('body').hide();

    $chartContainer.on("plothover", function(event, pos, item) {
        if (!item || !historicDataClone) { $tooltip.hide(); return; }
        var hourMs = item.datapoint[0] - 1800000;
        var dt = new Date(hourMs);
        var dayStr = ("0" + dt.getDate()).slice(-2) + "/" + ("0" + (dt.getMonth() + 1)).slice(-2);
        var hStr = ("0" + dt.getHours()).slice(-2);
        var idx = item.dataIndex;
        var concMins = (historicDataClone[0].data[idx] || [0, 0])[1];
        var distMins = (historicDataClone[1].data[idx] || [0, 0])[1];
        $tooltip.html(
            '<div class="tt-hour">' + dayStr + ' &nbsp; ' + hStr + ':00 – ' + hStr + ':59</div>' +
            '<div class="tt-row"><span class="tt-dot" style="background:#10b981"></span>' +
            '<span class="tt-label">Concentrado</span><span class="tt-val">' + Math.round(concMins) + ' min</span></div>' +
            '<div class="tt-row"><span class="tt-dot" style="background:#ef4444"></span>' +
            '<span class="tt-label">Distraído</span><span class="tt-val">' + Math.round(distMins) + ' min</span></div>'
        ).css({ top: event.pageY - 90, left: event.pageX + 18 }).show();
    });
    $chartContainer.on("mouseleave", function() { $tooltip.hide(); });

    // Arranque
    fetchHistoricalData();
    fetchRealtimeData();

    // Bucle de Tiempo Real Multi-gráfica (Cada 2 segundos)
    setInterval(fetchRealtimeData, 1000);

    // Refrescar histórico cada minuto
    setInterval(fetchHistoricalData, 60000);
});
