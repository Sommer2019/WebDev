<?php
declare(strict_types=1);
function isNumberToken(string $token): bool
{
    return (bool) preg_match('/^\d+(?:\.\d+)?$/', $token);
}
function isOperatorToken(string $token): bool
{
    return in_array($token, ['+', '-', '*', '/', '^'], true);
}
function isFunctionToken(string $token): bool
{
    return in_array($token, ['sqrt', 'root'], true);
}
function tokenize(string $expression): array
{
    $expression = trim(str_replace([' ', "\t", "\n", "\r", '√'], ['', '', '', '', 'sqrt'], $expression));
    if ($expression === '') {
        return [];
    }
    preg_match_all('/\d+(?:\.\d+)?|sqrt|root|[+\-*\/^(),]|./i', $expression, $matches);
    $tokens = $matches[0] ?? [];
    $normalized = [];
    foreach ($tokens as $token) {
        $token = strtolower($token);
        if (isNumberToken($token) || isOperatorToken($token) || in_array($token, ['(', ')', ','], true) || isFunctionToken($token)) {
            $normalized[] = $token;
            continue;
        }
        throw new InvalidArgumentException('Ungültiges Zeichen in der Eingabe.');
    }
    return $normalized;
}
function toRpn(array $tokens): array
{
    $output = [];
    $stack = [];
    $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, '^' => 3];
    $rightAssociative = ['^' => true];
    foreach ($tokens as $token) {
        if (isNumberToken($token)) {
            $output[] = $token;
            continue;
        }
        if (isFunctionToken($token)) {
            $stack[] = $token;
            continue;
        }
        if ($token === ',') {
            while (!empty($stack) && end($stack) !== '(') {
                $output[] = array_pop($stack);
            }
            if (empty($stack) || end($stack) !== '(') {
                throw new InvalidArgumentException('Trennzeichen ist an dieser Stelle nicht erlaubt.');
            }
            continue;
        }
        if (isOperatorToken($token)) {
            while (!empty($stack)) {
                $top = end($stack);
                if ($top === '(') {
                    break;
                }
                if (isFunctionToken((string) $top)) {
                    $output[] = array_pop($stack);
                    continue;
                }
                if (!isOperatorToken((string) $top)) {
                    break;
                }
                $topPrec = $precedence[$top];
                $tokenPrec = $precedence[$token];
                $shouldPop = isset($rightAssociative[$token]) ? $topPrec > $tokenPrec : $topPrec >= $tokenPrec;
                if (!$shouldPop) {
                    break;
                }
                $output[] = array_pop($stack);
            }
            $stack[] = $token;
            continue;
        }
        if ($token === '(') {
            $stack[] = $token;
            continue;
        }
        if ($token === ')') {
            while (!empty($stack) && end($stack) !== '(') {
                $output[] = array_pop($stack);
            }
            if (empty($stack) || end($stack) !== '(') {
                throw new InvalidArgumentException('Klammern sind nicht korrekt gesetzt.');
            }
            array_pop($stack);
            if (!empty($stack) && isFunctionToken((string) end($stack))) {
                $output[] = array_pop($stack);
            }
            continue;
        }
        throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
    }
    while (!empty($stack)) {
        $top = array_pop($stack);
        if ($top === '(' || $top === ')') {
            throw new InvalidArgumentException('Klammern sind nicht korrekt gesetzt.');
        }
        $output[] = $top;
    }
    return $output;
}
function evaluateRpn(array $rpn): float
{
    $stack = [];
    foreach ($rpn as $token) {
        if (isNumberToken($token)) {
            $stack[] = (float) $token;
            continue;
        }
        if (isOperatorToken($token)) {
            if (count($stack) < 2) {
                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }
            $b = array_pop($stack);
            $a = array_pop($stack);
            switch ($token) {
                case '+':
                    $stack[] = $a + $b;
                    break;
                case '-':
                    $stack[] = $a - $b;
                    break;
                case '*':
                    $stack[] = $a * $b;
                    break;
                case '/':
                    if ($b == 0.0) {
                        throw new InvalidArgumentException('Division durch 0 ist nicht erlaubt.');
                    }
                    $stack[] = $a / $b;
                    break;
                case '^':
                    $stack[] = $a ** $b;
                    break;
                default:
                    throw new InvalidArgumentException('Unbekannter Operator.');
            }
            continue;
        }
        if ($token === 'sqrt') {
            if (count($stack) < 1) {
                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }
            $value = array_pop($stack);
            if ($value < 0.0) {
                throw new InvalidArgumentException('Wurzeln aus negativen Zahlen sind nicht erlaubt.');
            }
            $stack[] = sqrt($value);
            continue;
        }
        if ($token === 'root') {
            if (count($stack) < 2) {
                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }
            $value = array_pop($stack);
            $degree = array_pop($stack);
            $degreeRounded = (int) round($degree);
            if ($degreeRounded <= 0 || abs($degree - $degreeRounded) > 1.0e-9) {
                throw new InvalidArgumentException('Die Wurzel benötigt eine positive ganze Zahl als Grad.');
            }
            if ($value < 0.0 && $degreeRounded % 2 === 0) {
                throw new InvalidArgumentException('Gerade Wurzeln aus negativen Zahlen sind nicht erlaubt.');
            }
            $rootValue = pow(abs($value), 1 / $degreeRounded);
            $stack[] = $value < 0.0 ? -$rootValue : $rootValue;
            continue;
        }
        throw new InvalidArgumentException('Unbekannter Operator.');
    }
    if (count($stack) !== 1) {
        throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
    }
    $result = array_pop($stack);
    if (!is_finite($result)) {
        throw new InvalidArgumentException('Das Ergebnis ist zu groß oder ungültig.');
    }
    return $result;
}
function formatResult(float $value): string
{
    $formatted = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}
$expression = '';
$result = '';
$error = '';
$nextExpression = '';
$calculationWasSuccessful = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expression = trim((string) ($_POST['expression'] ?? ''));
    $nextExpression = $expression;
    try {
        if ($expression === '') {
            throw new InvalidArgumentException('Bitte eine Rechnung eingeben.');
        }
        $tokens = tokenize($expression);
        $rpn = toRpn($tokens);
        $value = evaluateRpn($rpn);
        $result = formatResult($value);
        $nextExpression = $result;
        $calculationWasSuccessful = true;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taschenrechner</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/taschenrechner.css">
</head>
<body class="calculator-body">
<header>
    <h1>🧮 Taschenrechner</h1>
    <button class="theme-btn" id="themeToggle" aria-label="Farbschema wechseln">🌙 / ☀️</button>
</header>
<main class="calculator-page">
    <form method="post" class="calculator" id="calcForm" data-just-calculated="<?= $calculationWasSuccessful ? '1' : '0' ?>">
        <input type="hidden" name="expression" id="expressionInput" value="<?= htmlspecialchars($nextExpression, ENT_QUOTES, 'UTF-8') ?>">
        <div class="display">
            <div class="expression" id="expressionView"><?= htmlspecialchars($expression, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="result"><?= $error !== '' ? 'Fehler' : ($result !== '' ? htmlspecialchars($result, ENT_QUOTES, 'UTF-8') : '0') ?></div>
        </div>
        <?php if ($error !== ''): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="keys">
            <button type="button" class="calc-btn danger" data-action="clear">C</button>
            <button type="button" class="calc-btn fn" data-value="sqrt(" title="Gedrückt halten für n-te Wurzel">√x</button>
            <button type="button" class="calc-btn fn" data-value="^" title="Potenz">xʸ</button>
            <button type="button" class="calc-btn" data-action="backspace">⌫</button>
            <button type="button" class="calc-btn op" data-value="(">(</button>
            <button type="button" class="calc-btn op" data-value=")">)</button>
            <button type="button" class="calc-btn op" data-value="/">÷</button>
            <button type="button" class="calc-btn op" data-value="*">×</button>
            <button type="button" class="calc-btn" data-value="7">7</button>
            <button type="button" class="calc-btn" data-value="8">8</button>
            <button type="button" class="calc-btn" data-value="9">9</button>
            <button type="button" class="calc-btn op" data-value="-">−</button>
            <button type="button" class="calc-btn" data-value="4">4</button>
            <button type="button" class="calc-btn" data-value="5">5</button>
            <button type="button" class="calc-btn" data-value="6">6</button>
            <button type="button" class="calc-btn op" data-value="+">+</button>
            <button type="button" class="calc-btn" data-value="1">1</button>
            <button type="button" class="calc-btn" data-value="2">2</button>
            <button type="button" class="calc-btn" data-value="3">3</button>
            <button type="button" class="calc-btn" data-value="." title="Gedrückt halten für Komma">.</button>
            <button type="button" class="calc-btn wide-2" data-value="0">0</button>
            <button type="submit" class="calc-btn eq wide-2" data-action="equals">=</button>
        </div>
    </form>
    <a href="../index.html" class="form-link">Zurück zur Startseite</a>
</main>
<script src="../js/darkmode.js"></script>
<script src="../js/taschenrechner.js"></script>
</body>
</html>
