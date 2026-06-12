/* Plugin Name: die1-Geo Ads Pro - analytics.js */
/* Date: 20260610 */
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    if (typeof GAP_ANALYTICS === 'undefined') return;

    var labels = [];
    var clicks = [];
    var impressions = [];
    var cityMap = {};
    var regionMap = {};

    Object.keys(GAP_ANALYTICS).forEach(function(region){
        var banners = GAP_ANALYTICS[region].banners || [];
        var regionImpressions = 0;
        var regionClicks = 0;

        banners.forEach(function(b){
            labels.push(region + ' #' + b.id);
            clicks.push(b.clicks || 0);
            impressions.push(b.impressions || 0);
            regionImpressions += (b.impressions || 0);
            regionClicks += (b.clicks || 0);

            var cities = b.cities || {};
            Object.keys(cities).forEach(function(city){
                if (!cityMap[city]) cityMap[city] = 0;
                cityMap[city] += (cities[city].impressions || 0) + (cities[city].clicks || 0);
            });
        });

        if (regionImpressions > 0 || regionClicks > 0) {
            if (!regionMap[region]) regionMap[region] = 0;
            regionMap[region] += regionImpressions + regionClicks;
        }
    });

    var pieData = Object.keys(cityMap).length > 0 ? cityMap : regionMap;
    var pieLabel = Object.keys(cityMap).length > 0 ? 'city' : 'region';

    // =========================================================================
    // Bar Chart – Banner Performance
    // =========================================================================
    var barCtx = document.getElementById('gapChart');

    function drawBarChart() {
        if (!barCtx || !labels.length) return;

        var container = barCtx.parentElement;
        var dpr = window.devicePixelRatio || 1;
        var displayWidth = container.clientWidth - 32;
        var displayHeight = 400;

        barCtx.width = displayWidth * dpr;
        barCtx.height = displayHeight * dpr;
        barCtx.style.width = displayWidth + 'px';
        barCtx.style.height = displayHeight + 'px';

        var canvas = barCtx.getContext('2d');
        canvas.scale(dpr, dpr);

        var width = displayWidth;
        var height = displayHeight;
        var padding = 48;
        var chartHeight = height - padding * 2;
        var maxValue = Math.max.apply(null, clicks.concat(impressions).concat([1]));
        var groupWidth = (width - padding * 2) / labels.length;
        var barWidth = Math.max(8, Math.min(32, groupWidth / 3));

        canvas.clearRect(0, 0, width, height);
        canvas.fillStyle = '#fff';
        canvas.fillRect(0, 0, width, height);
        canvas.strokeStyle = '#dcdcde';
        canvas.beginPath();
        canvas.moveTo(padding, padding);
        canvas.lineTo(padding, height - padding);
        canvas.lineTo(width - padding, height - padding);
        canvas.stroke();

        labels.forEach(function(label, index){
            var x = padding + index * groupWidth + groupWidth / 2;
            var impressionHeight = (impressions[index] / maxValue) * chartHeight;
            var clickHeight = (clicks[index] / maxValue) * chartHeight;

            canvas.fillStyle = 'rgba(54, 162, 235, 0.7)';
            canvas.fillRect(x - barWidth - 2, height - padding - impressionHeight, barWidth, impressionHeight);

            canvas.fillStyle = 'rgba(255, 99, 132, 0.7)';
            canvas.fillRect(x + 2, height - padding - clickHeight, barWidth, clickHeight);

            canvas.save();
            canvas.translate(x - 6, height - padding + 8);
            canvas.rotate(-Math.PI / 4);
            canvas.fillStyle = '#50575e';
            canvas.font = '11px sans-serif';
            canvas.fillText(label, 0, 0);
            canvas.restore();
        });

        canvas.fillStyle = 'rgba(54, 162, 235, 0.9)';
        canvas.fillRect(width - 190, 18, 12, 12);
        canvas.fillStyle = '#1d2327';
        canvas.fillText('Impressions', width - 172, 29);
        canvas.fillStyle = 'rgba(255, 99, 132, 0.9)';
        canvas.fillRect(width - 90, 18, 12, 12);
        canvas.fillStyle = '#1d2327';
        canvas.fillText('Clicks', width - 72, 29);
    }

    // =========================================================================
    // Pie Chart – Visitors by City
    // =========================================================================
    var pieCtx = document.getElementById('gapPieChart');

    var PIE_COLORS = [
        '#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6',
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1',
        '#84cc16', '#e11d48', '#0ea5e9', '#a855f7', '#10b981'
    ];

    function drawPieChart() {
        if (!pieCtx) return;

        var container = pieCtx.parentElement;
        var dpr = window.devicePixelRatio || 1;
        var displayWidth = container.clientWidth - 32;
        var displayHeight = 400;

        pieCtx.width = displayWidth * dpr;
        pieCtx.height = displayHeight * dpr;
        pieCtx.style.width = displayWidth + 'px';
        pieCtx.style.height = displayHeight + 'px';

        var canvas = pieCtx.getContext('2d');
        canvas.scale(dpr, dpr);

        canvas.clearRect(0, 0, displayWidth, displayHeight);

        var entryNames = Object.keys(pieData);
        if (!entryNames.length) {
            canvas.fillStyle = '#999';
            canvas.font = '13px sans-serif';
            canvas.textAlign = 'center';
            canvas.fillText('No data available yet.', displayWidth / 2, displayHeight / 2);
            return;
        }

        entryNames.sort(function(a, b){ return pieData[b] - pieData[a]; });

        var total = 0;
        entryNames.forEach(function(c){ total += pieData[c]; });

        var maxSlices = 10;
        var displayEntries = entryNames.slice(0, maxSlices);
        var otherTotal = 0;
        if (entryNames.length > maxSlices) {
            for (var i = maxSlices; i < entryNames.length; i++) {
                otherTotal += pieData[entryNames[i]];
            }
        }

        var slices = [];
        displayEntries.forEach(function(c){
            slices.push({ label: c, value: pieData[c] });
        });
        if (otherTotal > 0) {
            slices.push({ label: 'Other', value: otherTotal });
        }

        var radius = Math.min(displayWidth * 0.35, displayHeight * 0.38);
        var centerX = radius + 20;
        var centerY = displayHeight / 2;
        var startAngle = -Math.PI / 2;

        slices.forEach(function(slice, idx){
            var sliceAngle = (slice.value / total) * 2 * Math.PI;
            var endAngle = startAngle + sliceAngle;

            canvas.beginPath();
            canvas.moveTo(centerX, centerY);
            canvas.arc(centerX, centerY, radius, startAngle, endAngle);
            canvas.closePath();
            canvas.fillStyle = PIE_COLORS[idx % PIE_COLORS.length];
            canvas.fill();

            canvas.strokeStyle = '#fff';
            canvas.lineWidth = 2;
            canvas.stroke();

            if (sliceAngle > 0.15) {
                var midAngle = startAngle + sliceAngle / 2;
                var labelX = centerX + Math.cos(midAngle) * (radius * 0.65);
                var labelY = centerY + Math.sin(midAngle) * (radius * 0.65);
                var pct = Math.round((slice.value / total) * 100);

                canvas.fillStyle = '#fff';
                canvas.font = 'bold 11px sans-serif';
                canvas.textAlign = 'center';
                canvas.textBaseline = 'middle';
                canvas.fillText(pct + '%', labelX, labelY);
            }

            startAngle = endAngle;
        });

        // Legend – right side of pie
        var legendX = centerX + radius + 30;
        var legendY = Math.max(30, centerY - (slices.length * 22) / 2);
        var lineHeight = 22;
        var maxLabelWidth = displayWidth - legendX - 10;

        canvas.textAlign = 'left';
        canvas.textBaseline = 'middle';

        slices.forEach(function(slice, idx){
            var y = legendY + idx * lineHeight;
            canvas.fillStyle = PIE_COLORS[idx % PIE_COLORS.length];
            canvas.fillRect(legendX, y - 5, 12, 12);

            canvas.fillStyle = '#1d2327';
            canvas.font = '12px sans-serif';
            var text = slice.label + ' (' + slice.value + ')';
            var maxChars = Math.max(8, Math.floor(maxLabelWidth / 7));
            if (text.length > maxChars) text = text.substring(0, maxChars - 1) + '…';
            canvas.fillText(text, legendX + 18, y + 1);
        });
    }

    var titleEl = document.getElementById('gapPieChartTitle');
    if (titleEl && typeof GAP_ANALYTICS_I18N !== 'undefined') {
        titleEl.textContent = pieLabel === 'city' ? GAP_ANALYTICS_I18N.byCity : GAP_ANALYTICS_I18N.byRegion;
    }

    drawBarChart();
    drawPieChart();

    var resizeTimer;
    $(window).on('resize', function(){
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function(){
            drawBarChart();
            drawPieChart();
        }, 150);
    });

});
