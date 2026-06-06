// Date: 20260606
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    if (typeof GAP_ANALYTICS === 'undefined') return;

    let labels = [];
    let clicks = [];
    let impressions = [];

    Object.keys(GAP_ANALYTICS).forEach(region => {
        GAP_ANALYTICS[region].banners.forEach(b => {
            labels.push(region + ' #' + b.id);
            clicks.push(b.clicks || 0);
            impressions.push(b.impressions || 0);
        });
    });

    const ctx = document.getElementById('gapChart');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Clicks',
                    data: clicks,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)'
                },
                {
                    label: 'Impressions',
                    data: impressions,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                }
            ]
        }
    });

});
