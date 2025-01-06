google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Unique ID');
    data.addColumn('number', 'Amount');
    data.addRows(graphData);

    var options = {
        title: 'Last 20 Transactions',
        curveType: 'function', // this makes the line chart curved
        legend: { position: 'bottom' },
        hAxis: {
            title: 'Unique ID',
            textStyle : {
                fontSize: 10 // or the number you want
            }
        },
        vAxis: {
            title: 'Amount',
        },
        colors: ['#0f9d58'] // this changes the color of the line
    };

    var chart = new google.visualization.ColumnChart(document.getElementById('chart_div'));

    chart.draw(data, options);
}
