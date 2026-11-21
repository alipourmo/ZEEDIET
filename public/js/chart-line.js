/*
 * Measurement line charts (S4-T3). Each canvas[data-chart-line] carries the chart data built by ProgressService from
 * published sets only. Chart.js is self-hosted in public/vendor/chartjs. Without this script the canvas stays empty,
 * and the review page still shows its values table.
 *
 * RTL: the time axis runs from the right (oldest) and the value axis sits on the right. Digits in the tick and tooltip
 * labels come from data-digits, so no Persian text is written here (the labels are sent from lang/fa).
 */
(function () {
    'use strict';

    if (typeof window.Chart === 'undefined') {
        return;
    }

    var rtl = document.documentElement.dir === 'rtl';

    function toPersian(value, digits) {
        return String(value).replace(/[0-9]/g, function (d) {
            return digits.charAt(Number(d));
        });
    }

    document.querySelectorAll('canvas[data-chart-line]').forEach(function (canvas) {
        var chart = JSON.parse(canvas.getAttribute('data-chart-line'));
        var digits = canvas.getAttribute('data-digits') || '0123456789';

        new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: chart.labels,
                datasets: chart.series.map(function (series) {
                    return {
                        label: series.label,
                        data: series.data,
                        borderColor: series.color,
                        backgroundColor: series.color,
                        spanGaps: true,
                        tension: 0.2,
                        pointRadius: 3
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { reverse: rtl },
                    y: {
                        position: rtl ? 'right' : 'left',
                        ticks: {
                            callback: function (value) {
                                return toPersian(value, digits);
                            }
                        }
                    }
                },
                plugins: {
                    legend: { rtl: rtl, labels: { usePointStyle: true } },
                    tooltip: {
                        rtl: rtl,
                        callbacks: {
                            label: function (context) {
                                if (context.parsed.y === null) {
                                    return '';
                                }
                                return context.dataset.label + ': ' + toPersian(context.parsed.y, digits);
                            }
                        }
                    }
                }
            }
        });
    });
})();
