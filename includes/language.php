<?php
// Language configuration and helper functions

// Available languages
define('AVAILABLE_LANGUAGES', [
    'en' => 'English',
    'bn' => 'বাংলা'
]);

// Default language
define('DEFAULT_LANGUAGE', 'bn');

// Language file path
define('LANG_PATH', __DIR__ . '/languages/');

// Initialize language system
function initLanguage() {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Check for language switch request
    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $_GET['lang'];
        // Redirect to remove lang parameter from URL
        $url = $_SERVER['REQUEST_URI'];
        $url = preg_replace('/(\?|&)lang=[^&]*/', '', $url);
        if (!empty($url) && $url != $_SERVER['REQUEST_URI']) {
            header("Location: $url");
            exit();
        }
    }

    // Set default language if not set
    if (!isset($_SESSION['language'])) {
        $_SESSION['language'] = DEFAULT_LANGUAGE;
    }

    // Validate current language
    if (!array_key_exists($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = DEFAULT_LANGUAGE;
    }
}

// Get current language
function getCurrentLanguage() {
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

// Load language file
function loadLanguage($lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }

    $langFile = LANG_PATH . $lang . '.php';

    if (file_exists($langFile)) {
        return include $langFile;
    }

    // Fallback to default language
    $defaultLangFile = LANG_PATH . DEFAULT_LANGUAGE . '.php';
    if (file_exists($defaultLangFile)) {
        return include $defaultLangFile;
    }

    return [];
}

// Get translated text
function __($key, $lang = null) {
    static $translations = null;

    if ($translations === null) {
        $translations = loadLanguage($lang);
    }

    // If language changed, reload translations
    if ($lang !== null && $lang !== getCurrentLanguage()) {
        $translations = loadLanguage($lang);
    }

    return $translations[$key] ?? $key;
}

// Get translated text with sprintf support
function __s($key, ...$args) {
    $text = __($key);
    if (count($args) > 0) {
        return sprintf($text, ...$args);
    }
    return $text;
}

// Generate language switcher links
function getLanguageSwitcher() {
    $currentLang = getCurrentLanguage();
    $currentUrl = $_SERVER['REQUEST_URI'];
    $urlParts = parse_url($currentUrl);

    $links = [];
    foreach (AVAILABLE_LANGUAGES as $code => $name) {
        $query = isset($urlParts['query']) ? $urlParts['query'] : '';
        parse_str($query, $params);
        $params['lang'] = $code;
        $newQuery = http_build_query($params);

        $links[$code] = [
            'name' => $name,
            'url' => $urlParts['path'] . ($newQuery ? '?' . $newQuery : ''),
            'active' => ($code === $currentLang)
        ];
    }

    return $links;
}

// Initialize language system
initLanguage();
?>