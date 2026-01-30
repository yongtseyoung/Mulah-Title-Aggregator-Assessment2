<?php

class ArticleScraper {
    
    private $baseUrl = "https://www.theverge.com";
    private $debug = true;
    
    public function scrapeTheVerge($startYear = 2022, $endYear = null) {
        if ($endYear === null) {
            $endYear = date('Y');
        }
        
        $articles = [];
        
        $this->log("Starting scrape from $startYear to $endYear");
        
        $this->log("Fetching RSS feeds...");
        $rssArticles = $this->scrapeRssFeeds();
        $articles = array_merge($articles, $rssArticles);
        $this->log("RSS: Found " . count($rssArticles) . " articles");
        
        $currentYear = date('Y');
        if ($endYear >= $currentYear - 1) {
            $this->log("Recent year detected - scraping latest pages...");
            $latestArticles = $this->scrapeLatestPages();
            $articles = array_merge($articles, $latestArticles);
            $this->log("Latest pages: Found " . count($latestArticles) . " articles");
        }
        
        $this->log("Scraping archive pages...");
        $archiveArticles = $this->scrapeArchivePages($startYear, $endYear);
        $articles = array_merge($articles, $archiveArticles);
        $this->log("Archive: Found " . count($archiveArticles) . " articles");
        
        $articles = $this->removeDuplicates($articles);
        $this->log("Total unique: " . count($articles));
        
        $articles = $this->filterByYearRange($articles, $startYear, $endYear);
        $this->log("After year filter: " . count($articles));
        
        $articles = $this->sortArticles($articles);
        
        return $articles;
    }
    

    private function scrapeLatestPages() {
        $articles = [];
        
        $latestUrls = [
            $this->baseUrl,
            $this->baseUrl . "/archives",
        ];
        
        for ($page = 1; $page <= 10; $page++) {
            $latestUrls[] = $this->baseUrl . "/archives?page=" . $page;
        }
        
        foreach ($latestUrls as $url) {
            $this->log("  Scraping latest: $url");
            $html = $this->fetchPage($url);
            
            if (!$html) continue;
            
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            $allLinks = $xpath->query('//a[@href]');
            $foundInPage = 0;
            
            foreach ($allLinks as $link) {
                $href = $link->getAttribute('href');
                
                if (preg_match('#/(\d{4})/(\d{1,2})/(\d{1,2})/([^/?\#]+)#', $href, $matches)) {
                    $year = (int)$matches[1];
                    $month = (int)$matches[2];
                    $day = (int)$matches[3];
                    
                    if ($year >= 2025) {
                        $title = trim($link->textContent);
                        
                        if (strlen($title) >= 10) {
                            $fullLink = $href;
                            if (strpos($fullLink, 'http') !== 0) {
                                $fullLink = $this->baseUrl . $fullLink;
                            }
                            
                            $date = strtotime("$year-$month-$day");
                            
                            $articles[] = [
                                'title' => $title,
                                'link' => $fullLink,
                                'date' => $date,
                                'date_formatted' => date('F d, Y', $date)
                            ];
                            
                            $foundInPage++;
                        }
                    }
                }
            }
            
            $this->log("    Found $foundInPage articles");
            
            if ($foundInPage == 0 && strpos($url, 'page=') !== false) {
                break;
            }
            
            usleep(300000);
        }
        
        return $articles;
    }
    
    private function scrapeRssFeeds() {
        $articles = [];
        
        $rssFeeds = [
            $this->baseUrl . "/rss/index.xml",
            $this->baseUrl . "/rss/full.xml",
            
            $this->baseUrl . "/tech/rss/index.xml",
            $this->baseUrl . "/reviews/rss/index.xml",
            $this->baseUrl . "/science/rss/index.xml",
            $this->baseUrl . "/entertainment/rss/index.xml",
            $this->baseUrl . "/policy/rss/index.xml",
            
            $this->baseUrl . "/apple/rss/index.xml",
            $this->baseUrl . "/google/rss/index.xml",
            $this->baseUrl . "/microsoft/rss/index.xml",
            $this->baseUrl . "/amazon/rss/index.xml",
            $this->baseUrl . "/facebook/rss/index.xml",
            $this->baseUrl . "/gaming/rss/index.xml",
            $this->baseUrl . "/web/rss/index.xml",
            $this->baseUrl . "/ai-artificial-intelligence/rss/index.xml",
        ];
        
        foreach ($rssFeeds as $rssUrl) {
            $feedArticles = $this->scrapeRssFeed($rssUrl);
            $articles = array_merge($articles, $feedArticles);
            $this->log("  " . basename(dirname($rssUrl)) . ": " . count($feedArticles) . " articles");
        }
        
        return $articles;
    }
    

    private function scrapeRssFeed($url) {
        $articles = [];
        
        try {
            $xml = $this->fetchPage($url);
            if (!$xml) {
                return $articles;
            }
            
            libxml_use_internal_errors(true);
            $rss = simplexml_load_string($xml);
            
            if ($rss === false) {
                return $articles;
            }
            
            if (isset($rss->channel->item)) {
                foreach ($rss->channel->item as $item) {
                    $article = $this->parseRssItem($item);
                    if ($article) {
                        $articles[] = $article;
                    }
                }
            }
            elseif (isset($rss->entry)) {
                foreach ($rss->entry as $entry) {
                    $article = $this->parseAtomEntry($entry);
                    if ($article) {
                        $articles[] = $article;
                    }
                }
            }
            
            libxml_clear_errors();
            
        } catch (Exception $e) {
            $this->log("  RSS Error: " . $e->getMessage());
        }
        
        return $articles;
    }
    
    private function parseRssItem($item) {
        try {
            $title = (string) $item->title;
            $link = (string) $item->link;
            
            $date = null;
            if (isset($item->pubDate)) {
                $date = strtotime((string) $item->pubDate);
            } elseif (isset($item->date)) {
                $date = strtotime((string) $item->date);
            }
            
            if (empty($title) || empty($link) || !$date) {
                return null;
            }
            
            return [
                'title' => trim($title),
                'link' => trim($link),
                'date' => $date,
                'date_formatted' => date('F d, Y', $date)
            ];
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function parseAtomEntry($entry) {
        try {
            $title = (string) $entry->title;
            
            $link = '';
            if (isset($entry->link)) {
                if (is_object($entry->link) && isset($entry->link['href'])) {
                    $link = (string) $entry->link['href'];
                } else {
                    $link = (string) $entry->link;
                }
            }
            
            $date = null;
            if (isset($entry->published)) {
                $date = strtotime((string) $entry->published);
            } elseif (isset($entry->updated)) {
                $date = strtotime((string) $entry->updated);
            }
            
            if (empty($title) || empty($link) || !$date) {
                return null;
            }
            
            return [
                'title' => trim($title),
                'link' => trim($link),
                'date' => $date,
                'date_formatted' => date('F d, Y', $date)
            ];
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function scrapeArchivePages($startYear, $endYear) {
        $articles = [];
        
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        for ($year = $endYear; $year >= $startYear; $year--) {
            for ($month = 12; $month >= 1; $month--) {
                
                $isCurrentYear = ($year == $currentYear);
                $isCurrentMonth = ($year == $currentYear && $month == $currentMonth);
                
                $daysToScrape = [];
                
                if ($isCurrentMonth) {
                    $currentDay = date('j');
                    for ($day = 1; $day <= $currentDay; $day++) {
                        $daysToScrape[] = $day;
                    }
                } elseif ($isCurrentYear) {
                    continue;
                } else {
                    $daysToScrape = [1, 4, 7, 10, 13, 16, 19, 22, 25, 28];
                }
                
                foreach ($daysToScrape as $day) {
                    $archiveUrl = $this->baseUrl . "/archives/" . $year . "/" . $month . "/" . $day;
                    $this->log("  Scraping: $archiveUrl");
                    
                    $pageArticles = $this->scrapeArchivePage($archiveUrl, $year, $month);
                    $articles = array_merge($articles, $pageArticles);
                    $this->log("    Found: " . count($pageArticles) . " articles");
                    
                    usleep(200000);
                }
            }
        }
        
        return $articles;
    }
    
    private function scrapeArchivePageWithPagination($baseUrl, $year, $month) {
        $allArticles = [];
        
        $pageArticles = $this->scrapeArchivePage($baseUrl, $year, $month);
        $allArticles = array_merge($allArticles, $pageArticles);
        

        for ($pageNum = 2; $pageNum <= 5; $pageNum++) {
            $paginatedUrl = $baseUrl . "/" . $pageNum;
            $this->log("    Trying pagination: $paginatedUrl");
            
            $moreArticles = $this->scrapeArchivePage($paginatedUrl, $year, $month);
            
            if (count($moreArticles) > 0) {
                $allArticles = array_merge($allArticles, $moreArticles);
                $this->log("    Page $pageNum: Found " . count($moreArticles) . " articles");
                usleep(200000);
            } else {
                break;
            }
        }
        
        return $allArticles;
    }
    

    private function scrapeArchivePage($url, $year, $month) {
        $articles = [];
        
        try {
            $html = $this->fetchPage($url);
            if (!$html) {
                $this->log("    Failed to fetch page");
                return $articles;
            }
            
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            
            $allLinks = $xpath->query('//a[@href]');
            $seenLinks = [];
            
            foreach ($allLinks as $link) {
                $href = $link->getAttribute('href');
                
                if (preg_match('#/(\d{4})/(\d{1,2})/(\d{1,2})/([^/?\#]+)#', $href, $matches)) {
                    $linkYear = (int)$matches[1];
                    $linkMonth = (int)$matches[2];
                    $linkDay = (int)$matches[3];
                    
                    $fullLink = $href;
                    if (strpos($fullLink, 'http') !== 0) {
                        $fullLink = $this->baseUrl . $fullLink;
                    }
                    
                    if (isset($seenLinks[$fullLink])) {
                        continue;
                    }
                    $seenLinks[$fullLink] = true;
                    
                    if ($linkYear == $year && $linkMonth == $month) {
                        $title = trim($link->textContent);
                        
                        if (strlen($title) < 10 || 
                            stripos($title, 'next page') !== false || 
                            stripos($title, 'previous') !== false ||
                            stripos($title, 'load more') !== false ||
                            stripos($title, 'view all') !== false) {
                            continue;
                        }
                        
                        $date = strtotime("$linkYear-$linkMonth-$linkDay");
                        
                        $articles[] = [
                            'title' => $title,
                            'link' => $fullLink,
                            'date' => $date,
                            'date_formatted' => date('F d, Y', $date)
                        ];
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("    Error: " . $e->getMessage());
        }
        
        return $articles;
    }
    
    private function fetchPage($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Connection: keep-alive',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || $content === false) {
            return false;
        }
        
        return $content;
    }
    

    private function filterByYearRange($articles, $startYear, $endYear) {
        $filtered = [];
        
        $startTimestamp = strtotime("$startYear-01-01");
        $endTimestamp = strtotime("$endYear-12-31 23:59:59");
        
        foreach ($articles as $article) {
            if ($article['date'] >= $startTimestamp && $article['date'] <= $endTimestamp) {
                $filtered[] = $article;
            }
        }
        
        return $filtered;
    }
    

    private function removeDuplicates($articles) {
        $seen = [];
        $unique = [];
        
        foreach ($articles as $article) {
            $link = $article['link'];
            if (!isset($seen[$link])) {
                $seen[$link] = true;
                $unique[] = $article;
            }
        }
        
        return $unique;
    }
    

    private function sortArticles($articles) {
        usort($articles, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return $articles;
    }
    

    private function log($message) {
        if ($this->debug) {
            echo "<!-- $message -->\n";
        }
    }
}
?>