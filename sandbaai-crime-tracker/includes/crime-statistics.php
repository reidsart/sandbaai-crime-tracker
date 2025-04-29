<?php
// Crime Statistics Functions

// Create Statistics Dashboard Admin Page
add_action('admin_menu', 'sandcrime_statistics_dashboard_menu');
function sandcrime_statistics_dashboard_menu() {
    add_submenu_page(
        'sandcrime_dashboard',
        'Crime Statistics',
        'Crime Statistics',
        'manage_options',
        'sandcrime_statistics',
        'sandcrime_statistics_dashboard_page'
    );
}

// Crime Statistics Dashboard Page
function sandcrime_statistics_dashboard_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';

    // Fetch data for charts and graphs
    $crime_data = $wpdb->get_results("SELECT category, DATE(date_time) as date, COUNT(*) as count FROM $table_name GROUP BY category, DATE(date_time)");

    $crime_categories = $wpdb->get_results("SELECT category, COUNT(*) as count FROM $table_name GROUP BY category");

    $crime_locations = $wpdb->get_results("SELECT location, COUNT(*) as count FROM $table_name GROUP BY location");

    ?>
    <div class="wrap">
        <h1>Crime Statistics Dashboard</h1>

        <!-- Filters -->
        <form method="get">
            <label for="filter_month">Month:</label>
            <select id="filter_month" name="filter_month">
                <option value="">All</option>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo date('F', mktime(0, 0, 0, $i, 10)); ?></option>
                <?php endfor; ?>
            </select>

            <label for="filter_year">Year:</label>
            <select id="filter_year" name="filter_year">
                <option value="">All</option>
                <?php for ($i = date('Y'); $i >= 2000; $i--): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>

            <label for="filter_type">Crime Type:</label>
            <select id="filter_type" name="filter_type">
                <option value="">All</option>
                <option value="theft">Theft</option>
                <option value="vandalism">Vandalism</option>
                <option value="assault">Assault</option>
                <option value="burglary">Burglary</option>
            </select>

            <button type="submit">Apply Filters</button>
        </form>

        <!-- Crime Statistics Charts -->
        <h2>Crime Trends</h2>
        <div id="crime_trends_chart" style="width: 100%; height: 500px;"></div>

        <h2>Crime Categories</h2>
        <div id="crime_categories_chart" style="width: 100%; height: 500px;"></div>

        <h2>Crime Locations</h2>
        <div id="crime_locations_map" style="width: 100%; height: 500px;"></div>
    </div>

    <!-- Load Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart', 'geochart']});
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // Crime Trends (Line Chart)
            var crimeTrendsData = google.visualization.arrayToDataTable([
                ['Date', 'Count'],
                <?php foreach ($crime_data as $data): ?>
                    ['<?php echo $data->date; ?>', <?php echo $data->count; ?>],
                <?php endforeach; ?>
            ]);

            var trendsOptions = {
                title: 'Crimes Over Time',
                curveType: 'function',
                legend: { position: 'bottom' }
            };

            var trendsChart = new google.visualization.LineChart(document.getElementById('crime_trends_chart'));
            trendsChart.draw(crimeTrendsData, trendsOptions);

            // Crime Categories (Pie Chart)
            var crimeCategoriesData = google.visualization.arrayToDataTable([
                ['Category', 'Count'],
                <?php foreach ($crime_categories as $category): ?>
                    ['<?php echo $category->category; ?>', <?php echo $category->count; ?>],
                <?php endforeach; ?>
            ]);

            var categoriesOptions = {
                title: 'Crime Categories',
                is3D: true,
            };

            var categoriesChart = new google.visualization.PieChart(document.getElementById('crime_categories_chart'));
            categoriesChart.draw(crimeCategoriesData, categoriesOptions);

            // Crime Locations (GeoChart)
            var crimeLocationsData = google.visualization.arrayToDataTable([
                ['Location', 'Count'],
                <?php foreach ($crime_locations as $location): ?>
                    ['<?php echo $location->location; ?>', <?php echo $location->count; ?>],
                <?php endforeach; ?>
            ]);

            var locationsOptions = {
                region: 'ZA', // South Africa
                displayMode: 'markers',
                colorAxis: {colors: ['green', 'red']}
            };

            var locationsChart = new google.visualization.GeoChart(document.getElementById('crime_locations_map'));
            locationsChart.draw(crimeLocationsData, locationsOptions);
        }
    </script>
    <?php
}
?>