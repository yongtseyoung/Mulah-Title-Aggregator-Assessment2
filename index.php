<?php
// index.php - The Verge Title Aggregator with Year Selection
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Increase execution time for scraping
set_time_limit(300); // 5 minutes

require_once 'scraper.php';

// Get year selection parameters
$currentYear = date('Y');
$startYear = isset($_GET['start_year']) ? intval($_GET['start_year']) : 2022;
$endYear = isset($_GET['end_year']) ? intval($_GET['end_year']) : $currentYear;

// Validate years
$startYear = max(2022, min($startYear, $currentYear));
$endYear = max($startYear, min($endYear, $currentYear));

// Get page number for pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$articlesPerPage = 50;

// Check if we need to refresh data
$cacheFile = "cache_articles_{$startYear}_{$endYear}.json";
$cacheTime = 1800; // 30 minutes cache
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

// Load from cache or scrape
if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    // Load from cache
    $allArticles = json_decode(file_get_contents($cacheFile), true);
    $fromCache = true;
} else {
    // Scrape fresh data
    $scraper = new ArticleScraper();
    $allArticles = $scraper->scrapeTheVerge($startYear, $endYear);
    
    // Save to cache
    file_put_contents($cacheFile, json_encode($allArticles));
    $fromCache = false;
}

// Calculate pagination
$totalArticles = count($allArticles);
$totalPages = ceil($totalArticles / $articlesPerPage);
$offset = ($page - 1) * $articlesPerPage;

// Get articles for current page
$articles = array_slice($allArticles, $offset, $articlesPerPage);

// Pagination helpers
$hasPrevious = $page > 1;
$hasNext = $page < $totalPages;
$previousPage = $page - 1;
$nextPage = $page + 1;

$siteName = 'The Verge';

// Build query string for pagination
$queryParams = [
    'start_year' => $startYear,
    'end_year' => $endYear
];
$queryString = http_build_query($queryParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Title Aggregator - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Title Aggregator</h1>
            <p class="subtitle">The Verge - Browse articles by year</p>
            
            <!-- Year Selector -->
            <div class="year-selector">
                <!-- Quick Year Buttons -->
                <div class="quick-years">
                    <span class="quick-label">Select year:</span>
                    <?php
                    $quickYears = [
                        ['label' => '2026', 'start' => 2026, 'end' => 2026],
                        ['label' => '2025', 'start' => 2025, 'end' => 2025],
                        ['label' => '2024', 'start' => 2024, 'end' => 2024],
                        ['label' => '2023', 'start' => 2023, 'end' => 2023],
                        ['label' => '2022', 'start' => 2022, 'end' => 2022],
                    ];
                    
                    foreach ($quickYears as $quick):
                        $active = ($quick['start'] == $startYear && $quick['end'] == $endYear) ? 'active' : '';
                        $url = "?start_year={$quick['start']}&end_year={$quick['end']}";
                    ?>
                        <a href="<?php echo $url; ?>" class="quick-year-btn <?php echo $active; ?>">
                            <?php echo $quick['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <p class="current-site">
                <strong><?php echo htmlspecialchars($siteName); ?></strong>
                <span class="date-range">
                    (<?php echo $startYear; ?><?php echo ($startYear != $endYear) ? " - $endYear" : ""; ?>)
                </span>
                <?php if ($fromCache): ?>
                    <span class="cache-info">(Cached - <a href="?<?php echo $queryString; ?>&refresh=1" class="refresh-link">Refresh Data</a>)</span>
                <?php else: ?>
                    <span class="cache-info">(Fresh data loaded)</span>
                <?php endif; ?>
            </p>
            <p class="article-count">
                Total Articles: <?php echo number_format($totalArticles); ?>
                <?php if ($totalPages > 1): ?>
                    | Page <?php echo $page; ?> of <?php echo number_format($totalPages); ?>
                <?php endif; ?>
            </p>
        </header>
        
        <main>
            <div class="articles-list">
                <?php if (empty($articles)): ?>
                    <div class="no-articles">
                        <p>No articles found for the selected period.</p>
                        <p class="help-text">This could be due to:</p>
                        <ul>
                            <li>Network connectivity issues</li>
                            <li>The Verge website is temporarily unavailable</li>
                            <li>Scraping is being blocked</li>
                            <li>No articles published in the selected year range</li>
                        </ul>
                        <p class="help-text"><a href="?<?php echo $queryString; ?>&refresh=1">Try refreshing the data</a></p>
                    </div>
                <?php else: ?>
                    <?php 
                    $startNumber = $offset + 1;
                    foreach ($articles as $index => $article): 
                        $articleNumber = $startNumber + $index;
                    ?>
                        <div class="article-item">
                            <div class="article-number"><?php echo $articleNumber; ?></div>
                            <div class="article-content">
                                <a href="<?php echo htmlspecialchars($article['link']); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="article-title">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </a>
                                <div class="article-meta">
                                    <span class="date"><?php echo htmlspecialchars($article['date_formatted']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-left">
                                <?php if ($hasPrevious): ?>
                                    <a href="?<?php echo $queryString; ?>&page=<?php echo $previousPage; ?>" class="pagination-link">
                                        PREVIOUS
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pagination-center">
                                PAGE <?php echo $page; ?> OF <?php echo number_format($totalPages); ?>
                            </div>
                            
                            <div class="pagination-right">
                                <?php if ($hasNext): ?>
                                    <a href="?<?php echo $queryString; ?>&page=<?php echo $nextPage; ?>" class="pagination-link">
                                        NEXT
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Title Aggregator | Created by Cyril</p>
            <p class="footer-note">
            </p>
        </footer>
    </div>
</body>
</html>