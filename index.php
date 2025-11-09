<?php
require_once 'config.php';
require_once 'security.php';

$security = new AdvancedSecurity($pdo);

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit;
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit;
}

$_SESSION['last_activity'] = time();

if (isset($_GET['logout'])) {
    $security->logSecurityEvent($security->getClientIP(), "ADMIN_LOGOUT", "User: " . $_SESSION['admin_email']);
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit;
}

// FRUIT DATA LOGIC
$pageTitle = "All Fruits";
$searchQuery = "";
$fruits = [];
$error = null;
$selectedFruit = $_GET['fruit'] ?? '';
$showOnlySelected = isset($_GET['showOnly']);

function fetchAllFruits() {
    $url = "https://www.fruityvice.com/api/fruit/all";
    
    // Add error logging to see what's happening
    error_log("Attempting to fetch fruits from: " . $url);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FruitInfo Website');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log the response details
    error_log("HTTP Code: " . $httpCode);
    error_log("CURL Error: " . $curlError);
    error_log("Response length: " . strlen($response));
    
    if ($response === false) {
        throw new Exception("Failed to connect to API: " . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP $httpCode - Service may be unavailable");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from API. JSON Error: " . json_last_error_msg());
    }
    
    if (empty($data)) {
        throw new Exception("API returned empty data");
    }
    
    error_log("Successfully fetched " . count($data) . " fruits from API");
    return $data;
}

function getFruitColor($fruitName) {
    $fruitColors = [
        'apple' => ['primary' => '#dc2626', 'secondary' => '#fef2f2', 'accent' => '#ef4444', 'text' => '#1a1f1c'],
        'strawberry' => ['primary' => '#dc2626', 'secondary' => '#fef2f2', 'accent' => '#ef4444', 'text' => '#1a1f1c'],
        'cherry' => ['primary' => '#dc2626', 'secondary' => '#fef2f2', 'accent' => '#ef4444', 'text' => '#1a1f1c'],
        'raspberry' => ['primary' => '#dc2626', 'secondary' => '#fef2f2', 'accent' => '#ef4444', 'text' => '#1a1f1c'],
        'watermelon' => ['primary' => '#16a34a', 'secondary' => '#f0fdf4', 'accent' => '#22c55e', 'text' => '#1a1f1c'],
        'orange' => ['primary' => '#ea580c', 'secondary' => '#fff7ed', 'accent' => '#f97316', 'text' => '#1a1f1c'],
        'mandarin' => ['primary' => '#ea580c', 'secondary' => '#fff7ed', 'accent' => '#f97316', 'text' => '#1a1f1c'],
        'mango' => ['primary' => '#f59e0b', 'secondary' => '#fffbeb', 'accent' => '#d97706', 'text' => '#1a1f1c'],
        'banana' => ['primary' => '#eab308', 'secondary' => '#fefce8', 'accent' => '#ca8a04', 'text' => '#1a1f1c'],
        'lemon' => ['primary' => '#eab308', 'secondary' => '#fefce8', 'accent' => '#ca8a04', 'text' => '#1a1f1c'],
        'pineapple' => ['primary' => '#eab308', 'secondary' => '#fefce8', 'accent' => '#ca8a04', 'text' => '#1a1f1c'],
        'kiwi' => ['primary' => '#16a34a', 'secondary' => '#f0fdf4', 'accent' => '#22c55e', 'text' => '#1a1f1c'],
        'lime' => ['primary' => '#84cc16', 'secondary' => '#f7fee7', 'accent' => '#65a30d', 'text' => '#1a1f1c'],
        'avocado' => ['primary' => '#15803d', 'secondary' => '#f0fdf4', 'accent' => '#16a34a', 'text' => '#1a1f1c'],
        'pear' => ['primary' => '#84cc16', 'secondary' => '#f7fee7', 'accent' => '#65a30d', 'text' => '#1a1f1c'],
        'blueberry' => ['primary' => '#7e22ce', 'secondary' => '#faf5ff', 'accent' => '#a855f7', 'text' => '#1a1f1c'],
        'plum' => ['primary' => '#7e22ce', 'secondary' => '#faf5ff', 'accent' => '#a855f7', 'text' => '#1a1f1c'],
        'grape' => ['primary' => '#7e22ce', 'secondary' => '#faf5ff', 'accent' => '#a855f7', 'text' => '#1a1f1c'],
        'coconut' => ['primary' => '#a16207', 'secondary' => '#fffbeb', 'accent' => '#d97706', 'text' => '#1a1f1c'],
        'peach' => ['primary' => '#fdba74', 'secondary' => '#fff7ed', 'accent' => '#fb923c', 'text' => '#1a1f1c'],
    ];
    
    $name = strtolower($fruitName);
    
    if (isset($fruitColors[$name])) {
        return $fruitColors[$name];
    }
    
    foreach ($fruitColors as $key => $colors) {
        if (strpos($name, $key) !== false) {
            return $colors;
        }
    }
    
    return ['primary' => '#800020', 'secondary' => '#f8f9fa', 'accent' => '#e8e8e8', 'text' => '#1a1f1c'];
}

$category = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Try to fetch data with better error handling
try {
    error_log("Starting fruit data fetch...");
    $allFruits = fetchAllFruits();
    
    if (!empty($searchQuery)) {
        $fruits = array_filter($allFruits, function($fruit) use ($searchQuery) {
            return stripos($fruit['name'], $searchQuery) !== false;
        });
        $fruits = array_values($fruits);
        $pageTitle = "Search Results for: " . htmlspecialchars($searchQuery);
    } else {
        switch($category) {
            case 'berries':
                $fruits = array_filter($allFruits, function($fruit) {
                    $berryNames = ['strawberry', 'blueberry', 'raspberry', 'blackberry', 'cranberry'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $berryNames) || strpos($name, 'berry') !== false;
                });
                $pageTitle = "Berries";
                break;
                
            case 'citrus':
                $fruits = array_filter($allFruits, function($fruit) {
                    $citrusNames = ['orange', 'lemon', 'lime', 'grapefruit', 'mandarin'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $citrusNames);
                });
                $pageTitle = "Citrus Fruits";
                break;
                
            case 'tropical':
                $fruits = array_filter($allFruits, function($fruit) {
                    $tropicalNames = ['banana', 'pineapple', 'mango', 'papaya', 'coconut'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $tropicalNames);
                });
                $pageTitle = "Tropical Fruits";
                break;
                
            case 'stone':
                $fruits = array_filter($allFruits, function($fruit) {
                    $stoneNames = ['peach', 'plum', 'cherry', 'apricot'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $stoneNames);
                });
                $pageTitle = "Stone Fruits";
                break;
                
            case 'melons':
                $fruits = array_filter($allFruits, function($fruit) {
                    $melonNames = ['watermelon', 'melon', 'cantaloupe'];
                    $name = strtolower($fruit['name']);
                    foreach ($melonNames as $melon) {
                        if (strpos($name, $melon) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
                $pageTitle = "Melons";
                break;
                
            default:
                $fruits = $allFruits;
                $pageTitle = "All Fruits";
                break;
        }
        
        $fruits = array_values($fruits);
    }
    
    if (is_array($fruits)) {
        usort($fruits, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }
    
} catch (Exception $e) {
    $error = "Unable to load fruit data: " . $e->getMessage();
    $fruits = [];
    
    // Log the detailed error
    error_log("Fruit data error: " . $e->getMessage());
    
    // Provide more user-friendly error message
    if (strpos($e->getMessage(), 'connect') !== false) {
        $error = "Unable to connect to the fruit database. Please check your internet connection and try again.";
    } elseif (strpos($e->getMessage(), 'HTTP') !== false) {
        $error = "The fruit database service is currently unavailable. Please try again later.";
    }
}

// FALLBACK DATA - In case API fails, use sample data
if (empty($fruits) && empty($error)) {
    $fruits = [
        [
            'name' => 'Apple',
            'family' => 'Rosaceae',
            'order' => 'Rosales',
            'genus' => 'Malus',
            'nutritions' => [
                'calories' => 52,
                'fat' => 0.2,
                'sugar' => 10.3,
                'carbohydrates' => 13.8,
                'protein' => 0.3
            ]
        ],
        [
            'name' => 'Banana',
            'family' => 'Musaceae',
            'order' => 'Zingiberales',
            'genus' => 'Musa',
            'nutritions' => [
                'calories' => 96,
                'fat' => 0.2,
                'sugar' => 17.2,
                'carbohydrates' => 22.0,
                'protein' => 1.0
            ]
        ],
        [
            'name' => 'Orange',
            'family' => 'Rutaceae',
            'order' => 'Sapindales',
            'genus' => 'Citrus',
            'nutritions' => [
                'calories' => 43,
                'fat' => 0.2,
                'sugar' => 9.2,
                'carbohydrates' => 8.3,
                'protein' => 1.0
            ]
        ]
    ];
    $error = "Using sample data - API connection failed";
}

if ($showOnlySelected && !empty($selectedFruit) && is_array($fruits)) {
    $filteredFruits = [];
    foreach($fruits as $fruit) {
        if ($fruit['name'] === $selectedFruit) {
            $filteredFruits[] = $fruit;
            break;
        }
    }
    $fruits = $filteredFruits;
    if (!empty($fruits)) {
        $pageTitle = htmlspecialchars($selectedFruit);
    }
}

if (empty($fruits) && !empty($searchQuery)) {
    $error = "No fruits found matching '" . htmlspecialchars($searchQuery) . "'";
} elseif (empty($fruits) && $category !== 'all') {
    $error = "No " . htmlspecialchars($category) . " fruits found in the database";
} elseif (empty($fruits)) {
    $error = "No fruits available in the database";
}

// Debug: Check what we have
error_log("Final fruits count: " . (is_array($fruits) ? count($fruits) : '0'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FruitInfo - <?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="mobile-overlay"></div>
<body class="bg-dark text-light">
    <!-- Scroll Progress Bar -->
    <div class="scroll-progress"></div>

    <!-- Modern Navigation Bar -->
    <div class="nav-container">
        <nav>
            <div class="logo">
                <i class="fas fa-apple-alt"></i>
                Fruit<span>Info</span>
            </div>
            <ul class="nav-links">
                <li><a href="?category=all" class="<?php echo $category == 'all' && empty($searchQuery) && empty($selectedFruit) ? 'active' : ''; ?>"><i class="fas fa-home"></i>HOME</a></li>
                <li><a href="?category=berries" class="<?php echo $category == 'berries' ? 'active' : ''; ?>"><i class="fas fa-seedling"></i>BERRIES</a></li>
                <li><a href="?category=citrus" class="<?php echo $category == 'citrus' ? 'active' : ''; ?>"><i class="fas fa-lemon"></i>CITRUS</a></li>
                <li><a href="?category=tropical" class="<?php echo $category == 'tropical' ? 'active' : ''; ?>"><i class="fas fa-umbrella-beach"></i>TROPICAL</a></li>
                <li><a href="?category=stone" class="<?php echo $category == 'stone' ? 'active' : ''; ?>"><i class="fas fa-gem"></i>STONE FRUITS</a></li>
                <li><a href="?category=melons" class="<?php echo $category == 'melons' ? 'active' : ''; ?>"><i class="fas fa-water"></i>MELONS</a></li>
            </ul>
            <div class="nav-buttons">
                <div class="search-container">
                    <form id="searchForm" class="d-flex">
                        <div class="input-group search-group">
                            <input type="text" id="searchInput" name="search" 
                                   class="form-control search-input" 
                                   placeholder="Search fruits..." 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <button type="submit" class="btn search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="user-menu">
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user dropdown-icon"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item logout-btn" href="?logout=true">
                                    <i class="fas fa-sign-out-alt"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="main-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Main Content Area -->
                <div class="col-12">
                    <div class="content-wrapper">
                        <!-- Page Header -->
                        <div class="page-header">
                            <div class="header-content">
                                <div class="header-text">
                                    <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                                    <p class="page-subtitle">
                                        <?php if ($showOnlySelected && !empty($selectedFruit)): ?>
                                            <i class="fas fa-star me-2"></i>Detailed view of <?php echo htmlspecialchars($selectedFruit); ?>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle me-2"></i>Click any fruit card to view detailed information
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="header-actions">
                                    <?php if ($showOnlySelected && !empty($selectedFruit)): ?>
                                        <button onclick="showAllFruits()" class="btn btn-primary action-btn">
                                            <i class="fas fa-grid me-2"></i>View All Fruits
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($fruits) && is_array($fruits) && !empty($fruits) && !$error && !$showOnlySelected): ?>
                                        <div class="fruits-count-badge">
                                            <span class="count"><?php echo count($fruits); ?></span>
                                            <span class="label">Fruits</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <?php if (isset($error)): ?>
                            <div class="error-alert">
                                <div class="alert-content">
                                    <i class="fas fa-exclamation-triangle alert-icon"></i>
                                    <div class="alert-text">
                                        <strong>Notice:</strong> <?php echo htmlspecialchars($error); ?>
                                    </div>
                                </div>
                                <a href="?category=all" class="btn btn-outline">View All Fruits</a>
                            </div>
                        <?php endif; ?>

                        <!-- Fruit Grid -->
                        <div id="fruitGrid" class="<?php echo $showOnlySelected && !empty($selectedFruit) ? 'single-fruit-view' : 'fruits-grid'; ?>">
                            <?php if (isset($fruits) && is_array($fruits) && !empty($fruits)): ?>
                                <?php foreach($fruits as $index => $fruit): ?>
                                    <?php
                                    $isSelected = $selectedFruit === $fruit['name'];
                                    $fruitColors = getFruitColor($fruit['name']);
                                    $displayClass = $showOnlySelected && !$isSelected ? 'd-none' : '';
                                    $cardClass = $showOnlySelected && $isSelected ? 'fruit-card single-card visible' : 'fruit-card visible';
                                    ?>
                                    
                                    <div class="<?php echo $displayClass; ?>">
                                        <div class="<?php echo $cardClass; ?> fruit-grid-item"
                                             data-fruit-name="<?php echo htmlspecialchars($fruit['name']); ?>"
                                             onclick="selectFruit('<?php echo htmlspecialchars($fruit['name']); ?>')"
                                             style="--fruit-primary: <?php echo $fruitColors['primary']; ?>;
                                                    --fruit-secondary: <?php echo $fruitColors['secondary']; ?>;
                                                    --fruit-accent: <?php echo $fruitColors['accent']; ?>;
                                                    --fruit-text: <?php echo $fruitColors['text']; ?>;">
                                            <div class="card-header">
                                                <div class="fruit-title">
                                                    <h3 class="fruit-name"><?php echo htmlspecialchars($fruit['name']); ?></h3>
                                                    <span class="fruit-family">
                                                        <?php echo isset($fruit['family']) ? htmlspecialchars($fruit['family']) : 'Fruit'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body">
                                                <div class="nutrition-section">
                                                    <h4 class="section-title">
                                                        <i class="fas fa-apple-alt me-2"></i>Nutrition (per 100g)
                                                    </h4>
                                                    <div class="nutrition-grid">
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Calories</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['calories']) ? htmlspecialchars($fruit['nutritions']['calories']) : 'N/A'; ?></span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Sugar</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['sugar']) ? htmlspecialchars($fruit['nutritions']['sugar']) . 'g' : 'N/A'; ?></span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Carbs</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['carbohydrates']) ? htmlspecialchars($fruit['nutritions']['carbohydrates']) . 'g' : 'N/A'; ?></span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Protein</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['protein']) ? htmlspecialchars($fruit['nutritions']['protein']) . 'g' : 'N/A'; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="fruit-details">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Order</span>
                                                        <span class="detail-value"><?php echo isset($fruit['order']) ? htmlspecialchars($fruit['order']) : 'N/A'; ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Genus</span>
                                                        <span class="detail-value"><?php echo isset($fruit['genus']) ? htmlspecialchars($fruit['genus']) : 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!$showOnlySelected || !$isSelected): ?>
                                                    <div class="card-footer">
                                                        <div class="click-hint">
                                                            <i class="fas fa-mouse-pointer me-2"></i>
                                                            Click for details
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (!isset($error)): ?>
                                <!-- Empty State -->
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-fruit-watermelon"></i>
                                    </div>
                                    <h3 class="empty-title">No fruits found</h3>
                                    <p class="empty-text">Try a different search term or browse by category</p>
                                    <a href="?category=all" class="btn btn-primary">
                                        <i class="fas fa-th-large me-2"></i>Browse All Fruits
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="professional-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <i class="fas fa-apple-alt footer-icon"></i>
                    <span class="footer-text">Web<span>Dev</span></span>
                </div>
                <div class="footer-info">
                    <p class="footer-desc">Edrian Castillon</p>
                    <p class="footer-source">
                        <i class="fas fa-database me-1"></i>
                        Live data from <a href="https://fruityvice.com" target="_blank">FruityVice API</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button - FIXED WITH ARROW ICON -->
    <div class="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectFruit(fruitName) {
            window.location.href = '?fruit=' + encodeURIComponent(fruitName) + '&showOnly=true';
        }
        
        function showAllFruits() {
            window.location.href = '?category=all';
        }
        
        // Scroll progress bar
        window.addEventListener('scroll', function() {
            const winHeight = window.innerHeight;
            const docHeight = document.documentElement.scrollHeight;
            const scrollTop = window.pageYOffset;
            const scrollPercent = (scrollTop) / (docHeight - winHeight) * 100;
            document.querySelector('.scroll-progress').style.width = scrollPercent + '%';
            
            // Show/hide back to top button
            const backToTop = document.querySelector('.back-to-top');
            if (scrollTop > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        // Back to top functionality
        document.querySelector('.back-to-top').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Animate cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.fruit-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('visible');
                }, index * 100);
            });
        });
    </script>
</body>
</html>