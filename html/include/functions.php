<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Omdirigera till en annan sida
 * 
 * @param string $url URL att omdirigera till
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Sanera användarinmatning
 * 
 * @param string $input Användarinmatning
 * @return string Sanerad inmatning
 */
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Förnya sessionen och uppdatera utgångstiden
 */
function renewSession() {
    // Säkerställ att sessionen är startad
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Kontrollera om användaren är inloggad
    if (isset($_SESSION['user_id'])) {
        $currentTime = time();
        
        // Hämta sessionens livstid från .env eller använd standardvärdet (4 timmar)
        $sessionLifetimeHours = (int)getenv('SESSION_LIFETIME_HOURS') ?: 4;
        $sessionLifetime = $sessionLifetimeHours * 60 * 60; // Konvertera till sekunder
        
        // Hämta regenereringsintervall från .env eller använd standardvärdet (30 minuter)
        $regenerateMinutes = (int)getenv('SESSION_REGENERATE_MINUTES') ?: 30;
        $regenerateInterval = $regenerateMinutes * 60; // Konvertera till sekunder
        
        // Kontrollera om sessionen har gått ut
        if (!isset($_SESSION['last_activity']) || 
            ($currentTime - $_SESSION['last_activity']) > $sessionLifetime) {
            
            // Sessionen har gått ut, regenerera ID:t
            session_regenerate_id(true);
            $_SESSION['last_activity'] = $currentTime;
        } 
        // Eller om det har gått tillräckligt lång tid sedan senaste ID-regenereringen
        else if (!isset($_SESSION['last_regenerated']) || 
                 ($currentTime - $_SESSION['last_regenerated']) > $regenerateInterval) {
            
            // Regenerera sessions-ID för säkerhet med jämna intervall
            session_regenerate_id(true);
            
            // Uppdatera senaste regenereringstidpunkten
            $_SESSION['last_regenerated'] = $currentTime;
            $_SESSION['last_activity'] = $currentTime;
        }
        // Annars uppdatera bara aktivitetstidsstämpeln
        else {
            $_SESSION['last_activity'] = $currentTime;
        }
    }
}

/**
 * Generera en CSRF-token
 * 
 * @return string CSRF-token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validera en CSRF-token
 * 
 * @param string $token Token att validera
 * @return bool True om token är giltig, false annars
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sendOpenAIRequest($messages) {
    // Hämta API-konfiguration från .env
    $apiServer = getenv('AI_SERVER') ?: 'https://greta.sambruk.se/api/chat/completions';
    $apiKey = getenv('AI_API_KEY') ?: '';
    $model = getenv('AI_MODEL') ?: 'gpt-4';
    $maxTokens = (int)(getenv('AI_MAX_COMPLETION_TOKENS') ?: 4096);
    $temperature = (float)(getenv('AI_TEMPERATURE') ?: 0.7);
    $topP = (float)(getenv('AI_TOP_P') ?: 0.9);
    $maxRetries = 3;
    $timeout = 30; // sekunder

    if (empty($apiKey)) {
        throw new Exception('API-nyckel saknas i konfigurationen.');
    }

    // Avgör om vi använder openroute eller greta
    $isOpenRoute = strpos($apiServer, 'openrouter.ai') !== false;

    // Skapa API-förfrågan baserat på API-typ
    if ($isOpenRoute) {
        $requestData = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'top_p' => $topP,
            'max_tokens' => $maxTokens
        ];
    } else {
        $requestData = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => $topP
        ];
    }
    
    // Spara användar-ID från session för loggning
    $userId = $_SESSION['user_id'] ?? 'ingen_användar_id';
    $userEmail = $_SESSION['user_email'] ?? 'okänd användare';

    // Hantera återförsök
    $attempts = 0;
    $lastError = '';
    
    while ($attempts < $maxRetries) {
        $attempts++;
        
        // Anropa API
        $ch = curl_init($apiServer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        
        // Sätt headers baserat på API-typ
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Om vi fick ett giltigt svar, returnera det
        if ($httpCode === 200 && empty($error)) {
            $responseData = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Extrahera svaret från API-svaret baserat på API-typ
                if ($isOpenRoute) {
                    if (isset($responseData['choices'][0]['message']['content'])) {
                        logActivity($userEmail, "AI-anrop lyckades efter $attempts försök");
                        return $responseData['choices'][0]['message']['content'];
                    } elseif (isset($responseData['choices'][0]['text'])) {
                        logActivity($userEmail, "AI-anrop lyckades efter $attempts försök");
                        return $responseData['choices'][0]['text'];
                    }
                } else {
                    if (isset($responseData['choices'][0]['message']['content'])) {
                        logActivity($userEmail, "AI-anrop lyckades efter $attempts försök");
                        return $responseData['choices'][0]['message']['content'];
                    } elseif (isset($responseData['content'])) {
                        logActivity($userEmail, "AI-anrop lyckades efter $attempts försök");
                        return $responseData['content'];
                    }
                }
            }
        }
        
        // Om vi inte fick ett giltigt svar, spara felet och försök igen
        $lastError = "HTTP $httpCode: " . ($error ?: $response);
        sleep(1); // Vänta en sekund innan nästa försök
    }
    
    // Om vi har nått max antal försök, kasta ett undantag
    throw new Exception("Kunde inte få svar från AI efter $maxRetries försök. Senaste fel: $lastError");
}

/**
 * Konvertera Markdown-text till HTML
 * 
 * Denna funktion konverterar Markdown-text till HTML utan att förlita sig på externa bibliotek
 * som marked.js eller highlight.js. Den stödjer följande markdown-element:
 * - Kodblock (med språkspecifikation)
 * - Inline kod
 * - Rubriker (h1-h6)
 * - Fet och kursiv text
 * - Länkar (med säker hantering)
 * - Listor (numrerade och punkter)
 * - Blockquotes
 * - Horisontella linjer
 * 
 * @param string $text Markdown-text som ska konverteras
 * @return string HTML-formaterad text
 */
function parseMarkdown($text) {
    // Sanera inkommande text för att förhindra XSS
    $text = strip_tags($text);
    
    // Ta bort överflödiga radbrytningar
    $text = preg_replace('/\n\n+/', "\n\n", $text);
    
    // Ersätt kodblock med syntax highlighting
    $text = preg_replace_callback('/```(\w+)?\n([\s\S]*?)```/', function($matches) {
        $lang = $matches[1] ?? '';
        $code = htmlspecialchars($matches[2]);
        $langClass = !empty($lang) ? ' class="language-' . htmlspecialchars($lang) . '"' : '';
        return '<pre><code' . $langClass . '>' . $code . '</code></pre>';
    }, $text);

    // Ersätt inline kod
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    // Hantera listor först
    $text = preg_replace_callback('/(?:^|\n)(?:([0-9]+\.) |\- )(.*?)(?=\n|$)/', function($matches) {
        $isOrdered = isset($matches[1]);
        $content = $matches[2];
        $listType = $isOrdered ? 'ol' : 'ul';
        $item = $isOrdered ? "<li>$content</li>" : "<li>$content</li>";
        return "\n<$listType>$item</$listType>";
    }, $text);

    // Kombinera intilliggande listor av samma typ
    $text = preg_replace('/<\/(ol|ul)>\s*<\1>/', '', $text);

    // Ersätt rubriker (upp till 6 nivåer)
    $text = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^#### (.*$)/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^##### (.*$)/m', '<h5>$1</h5>', $text);
    $text = preg_replace('/^###### (.*$)/m', '<h6>$1</h6>', $text);

    // Ersätt fetstil och kursiv
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
    
    // Ersätt genomstruken text
    $text = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $text);

    // Konvertera återstående radbrytningar till <br> och <p>
    $text = '<p>' . str_replace("\n\n", '</p><p>', $text) . '</p>';
    $text = str_replace("\n", '<br>', $text);
    
    // Ta bort tomma paragrafer
    $text = preg_replace('/<p>\s*<\/p>/', '', $text);
    
    return $text;
}

/**
 * Logga en aktivitet i databasen
 * 
 * @param string $email Användarens e-post
 * @param string $message Meddelande om aktiviteten
 * @param array $context Extra kontext att inkludera i loggen (frivilligt)
 * @return bool True om det lyckades, false vid fel
 */
function logActivity($email, $message, $context = []) {
    try {
        // Standardisera e-post
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'okänd_användare';
        
        // Lägg till användar-ID om tillgängligt
        if (!isset($context['user_id']) && isset($_SESSION['user_id'])) {
            $context['user_id'] = $_SESSION['user_id'];
        }
        
        // Lägg till IP-adress om tillgänglig
        if (!isset($context['ip']) && isset($_SERVER['REMOTE_ADDR'])) {
            $context['ip'] = $_SERVER['REMOTE_ADDR'];
        }
        
        // Lägg till User-Agent om tillgänglig
        if (!isset($context['user_agent']) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        
        // Skapa ett detaljerat meddelande om det finns ytterligare kontext
        $detailedMessage = $message;
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // Lägg till kontext som JSON i meddelandet men begränsa till 1000 tecken för att undvika för stora loggar
            if (strlen($contextStr) > 1000) {
                $contextStr = substr($contextStr, 0, 997) . '...';
            }
            $detailedMessage .= ' | Kontext: ' . $contextStr;
        }
        
        execute("INSERT INTO " . DB_DATABASE . ".logs (email, message) VALUES (?, ?)", 
                [$email, $detailedMessage]);
        return true;
    } catch (Exception $e) {
        error_log("Logging failed: " . $e->getMessage());
        return false;
    }
}

// Sökväg till upload-mappen
$uploadDir = __DIR__ . '/../upload/';

// Kontrollera om mappen finns, annars skapa den
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
