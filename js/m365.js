/* M365 License Manager - gráficos do dashboard.
 * Usa o Chart.js já embarcado pelo GLPI 10. Lê os dados de data-values (JSON). */
(function () {
    'use strict';

    function parse(el) {
        try { return JSON.parse(el.getAttribute('data-values') || '{}'); }
        catch (e) { return {}; }
    }

    var palette = ['#0078D4', '#107C10', '#F2A900', '#D13438', '#5C2D91',
                   '#008272', '#B4009E', '#FF8C00', '#00B7C3', '#6B69D6'];

    function ready() {
        if (typeof Chart === 'undefined') { return; }

        var type = document.getElementById('m365ChartType');
        if (type) {
            var d = parse(type);
            new Chart(type, {
                type: 'doughnut',
                data: { labels: Object.keys(d),
                        datasets: [{ data: Object.values(d), backgroundColor: palette }] },
                options: { plugins: { legend: { position: 'right' } } }
            });
        }

        var dept = document.getElementById('m365ChartDept');
        if (dept) {
            var dd = parse(dept);
            new Chart(dept, {
                type: 'bar',
                data: { labels: Object.keys(dd),
                        datasets: [{ label: 'Usuários', data: Object.values(dd), backgroundColor: '#0078D4' }] },
                options: { plugins: { legend: { display: false } } }
            });
        }

        var trend = document.getElementById('m365ChartTrend');
        if (trend) {
            var td = parse(trend);
            new Chart(trend, {
                type: 'line',
                data: { labels: Object.keys(td),
                        datasets: [{ label: 'Custo (R$)', data: Object.values(td),
                                     borderColor: '#107C10', backgroundColor: 'rgba(16,124,16,.15)', fill: true, tension: .3 }] }
            });
        }
    }

    if (document.readyState !== 'loading') { ready(); }
    else { document.addEventListener('DOMContentLoaded', ready); }
})();
