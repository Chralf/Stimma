<?php
/**
 * Stimma - Lär dig i små steg
 * Copyright (C) 2025 Christian Alfredsson
 * 
 * This program is free software; licensed under GPL v2.
 * See LICENSE and LICENSE-AND-TRADEMARK.md for details.
 * 
 * The name "Stimma" is a trademark and subject to restrictions.
 */
?>

<?php
require_once '../include/config.php';
require_once '../include/database.php';
require_once '../include/functions.php';
require_once '../include/auth.php';

// Sätt sidtitel
$page_title = 'Admin';

// Extra CSS för Chart.js
$extra_head = '<script src="../include/js/chart.min.js"></script>';

// Hämta statistik för dashboard
$totalUsers = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".users")['count'] ?? 0;
$totalCourses = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".courses")['count'] ?? 0;
$totalLessons = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".lessons")['count'] ?? 0;
$totalCompletions = queryOne("SELECT COUNT(*) as count FROM " . DB_DATABASE . ".progress WHERE status = 'completed'")['count'] ?? 0;

// Hämta statistik per kurs
$courseStats = query("SELECT 
    c.id, 
    c.title, 
    c.status,
    COUNT(DISTINCT l.id) as total_lessons,
    COUNT(DISTINCT p.id) as total_completions,
    COUNT(DISTINCT p.user_id) as unique_users
FROM " . DB_DATABASE . ".courses c
LEFT JOIN " . DB_DATABASE . ".lessons l ON c.id = l.course_id
LEFT JOIN " . DB_DATABASE . ".progress p ON l.id = p.lesson_id AND p.status = 'completed'
GROUP BY c.id, c.title, c.status
ORDER BY total_completions DESC");

// Hämta de senaste aktiviteterna
$recentActivity = query("SELECT p.*, u.email, l.title as lesson_title, c.title as course_title 
                        FROM " . DB_DATABASE . ".progress p 
                        JOIN " . DB_DATABASE . ".users u ON p.user_id = u.id 
                        JOIN " . DB_DATABASE . ".lessons l ON p.lesson_id = l.id 
                        JOIN " . DB_DATABASE . ".courses c ON l.course_id = c.id 
                        ORDER BY p.updated_at DESC LIMIT 5");

// Beräkna aktivitet per dag (senaste 7 dagarna)
$dateActivity = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateActivity[$date] = 0;
}

$weekActivity = query("SELECT DATE(updated_at) as date, COUNT(*) as count 
                      FROM " . DB_DATABASE . ".progress 
                      WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                      GROUP BY DATE(updated_at)");

foreach ($weekActivity as $day) {
    if (isset($dateActivity[$day['date']])) {
        $dateActivity[$day['date']] = $day['count'];
    }
}

// Beräkna aktivitet per kurs (senaste 7 dagarna)
$courseActivity = query("SELECT 
    c.title,
    COUNT(p.id) as activity_count
FROM " . DB_DATABASE . ".courses c
LEFT JOIN " . DB_DATABASE . ".lessons l ON c.id = l.course_id
LEFT JOIN " . DB_DATABASE . ".progress p ON l.id = p.lesson_id 
    AND p.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY c.id, c.title
ORDER BY activity_count DESC");

$users = queryAll("SELECT * FROM " . DB_DATABASE . ".users ORDER BY created_at DESC");
$courses = queryAll("SELECT * FROM " . DB_DATABASE . ".courses ORDER BY sort_order ASC");
$lessons = queryAll("SELECT * FROM " . DB_DATABASE . ".lessons ORDER BY sort_order ASC");
$progress = queryAll("SELECT * FROM " . DB_DATABASE . ".progress ORDER BY updated_at DESC");

// Inkludera header
require_once 'include/header.php';
?>



<!-- Statistik Dashboard -->            
<div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">Dashboard</h4>
                </div>
                
                <!-- Statistikkort -->                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Användare</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalUsers ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-people-fill text-primary" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Lektioner</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalLessons ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-book-fill text-success" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Slutförda lektioner</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalCompletions ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle-fill text-info" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Genomsnitt/användare</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $totalUsers > 0 ? round($totalCompletions / $totalUsers, 1) : 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-graph-up text-warning" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grafer och tabeller -->            
            <div class="row mb-4">
                <!-- Aktivitetsgraf -->                
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Aktivitet senaste 7 dagarna</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kursstatistik -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Kursstatistik</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kurs</th>
                                            <th>Status</th>
                                            <th>Lektioner</th>
                                            <th>Slutförda</th>
                                            <th>Unika användare</th>
                                            <th>Genomsnitt/Användare</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courseStats as $course): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($course['title']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $course['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                    <?= $course['status'] === 'active' ? 'Aktiv' : 'Inaktiv' ?>
                                                </span>
                                            </td>
                                            <td><?= $course['total_lessons'] ?></td>
                                            <td><?= $course['total_completions'] ?></td>
                                            <td><?= $course['unique_users'] ?></td>
                                            <td>
                                                <?= $course['unique_users'] > 0 
                                                    ? round($course['total_completions'] / $course['unique_users'], 1) 
                                                    : 0 ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kursaktivitet -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Kursaktivitet (senaste 7 dagarna)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="courseActivityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Senaste aktiviteter -->                
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Senaste aktiviteter</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Användare</th>
                                            <th>Kurs</th>
                                            <th>Lektion</th>
                                            <th>Status</th>
                                            <th>Datum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivity as $activity): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($activity['email']) ?></td>
                                            <td><?= htmlspecialchars($activity['course_title']) ?></td>
                                            <td><?= htmlspecialchars($activity['lesson_title']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $activity['status'] === 'completed' ? 'success' : 'warning' ?>">
                                                    <?= $activity['status'] === 'completed' ? 'Slutförd' : 'Påbörjad' ?>
                                                </span>
                                            </td>
                                            <td><?= date('Y-m-d H:i', strtotime($activity['updated_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>
        </div>
    </div>

<?php
// Förbered data för graferna
$labels_date_activity = "'" . implode("', '", array_map(function($date) { return date('d M', strtotime($date)); }, array_keys($dateActivity))) . "'";
$values_date_activity = implode(', ', array_values($dateActivity));

$labels_course_activity = "'" . implode("', '", array_column($courseActivity, 'title')) . "'";
$values_course_activity = implode(', ', array_column($courseActivity, 'activity_count'));

// Definiera extra JavaScript för Chart.js
$extra_scripts = <<<EOT
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Aktivitetsgraf
        var ctx = document.getElementById('activityChart').getContext('2d');
        var activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [{$labels_date_activity}],
                datasets: [{
                    label: 'Aktiviteter',
                    data: [{$values_date_activity}],
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }]
                },
                legend: {
                    display: false
                },
                tooltips: {
                    backgroundColor: 'rgb(255, 255, 255)',
                    bodyFontColor: '#858796',
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10
                }
            }
        });

        // Kursaktivitetsgraf
        var courseCtx = document.getElementById('courseActivityChart').getContext('2d');
        var courseActivityChart = new Chart(courseCtx, {
            type: 'bar',
            data: {
                labels: [{$labels_course_activity}],
                datasets: [{
                    label: 'Aktiviteter',
                    data: [{$values_course_activity}],
                    backgroundColor: 'rgba(78, 115, 223, 0.5)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }]
                },
                legend: {
                    display: false
                },
                tooltips: {
                    backgroundColor: 'rgb(255, 255, 255)',
                    bodyFontColor: '#858796',
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10
                }
            }
        });
    });
</script>
EOT;

// Inkludera footer
require_once 'include/footer.php';
