// Date: 20260606
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    if (typeof GAP_ANALYTICS === 'undefined') return;

    const labels = [];
    const clicks = [];
    const impressions = [];

    Object.keys(GAP_ANALYTICS).forEach(region => {
        const banners = GAP_ANALYTICS[region].banners || [];

        banners.forEach(b => {
            labels.push(region + ' #' + b.id);
            clicks.push(b.clicks || 0);
            impressions.push(b.impressions || 0);
        });
    });

    const ctx = document.getElementById('gapChart');
    if (!ctx || !labels.length) return;

    const canvas = ctx.getContext('2d');
    const width = ctx.width;
    const height = ctx.height;
    const padding = 48;
    const chartHeight = height - padding * 2;
    const maxValue = Math.max(...clicks, ...impressions, 1);
    const groupWidth = (width - padding * 2) / labels.length;
    const barWidth = Math.max(8, Math.min(24, groupWidth / 3));

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
        const x = padding + index * groupWidth + groupWidth / 2;
        const impressionHeight = (impressions[index] / maxValue) * chartHeight;
        const clickHeight = (clicks[index] / maxValue) * chartHeight;

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

});
