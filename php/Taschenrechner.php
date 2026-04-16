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
    return in_array($token, ['sqrt', 'root', 'neg'], true);
}

function normalizeNearZero(float $value): float
{
    return abs($value) < 1.0e-10 ? 0.0 : $value;
}

function makeComplex(float $re, float $im = 0.0): array
{
    return [
        're' => normalizeNearZero($re),
        'im' => normalizeNearZero($im),
    ];
}

function complexIsZero(array $value): bool
{
    return abs($value['re']) < 1.0e-10 && abs($value['im']) < 1.0e-10;
}

function complexAdd(array $a, array $b): array
{
    return makeComplex($a['re'] + $b['re'], $a['im'] + $b['im']);
}

function complexSub(array $a, array $b): array
{
    return makeComplex($a['re'] - $b['re'], $a['im'] - $b['im']);
}

function complexMul(array $a, array $b): array
{
    return makeComplex(
        $a['re'] * $b['re'] - $a['im'] * $b['im'],
        $a['re'] * $b['im'] + $a['im'] * $b['re']
    );
}

function complexDiv(array $a, array $b): array
{
    $denominator = $b['re'] * $b['re'] + $b['im'] * $b['im'];
    if ($denominator < 1.0e-12) {
        throw new InvalidArgumentException('Division durch 0 ist nicht erlaubt.');
    }

    return makeComplex(
        ($a['re'] * $b['re'] + $a['im'] * $b['im']) / $denominator,
        ($a['im'] * $b['re'] - $a['re'] * $b['im']) / $denominator
    );
}

function complexSqrt(array $value): array
{
    $modulus = hypot($value['re'], $value['im']);
    $realPart = sqrt(max(0.0, ($modulus + $value['re']) / 2.0));
    $imagPartMagnitude = sqrt(max(0.0, ($modulus - $value['re']) / 2.0));
    $imagPart = $value['im'] < 0.0 ? -$imagPartMagnitude : $imagPartMagnitude;

    return makeComplex($realPart, $imagPart);
}

function complexPow(array $base, array $exponent): array
{
    if (complexIsZero($base)) {
        if (abs($exponent['im']) > 1.0e-10 || $exponent['re'] < 0.0) {
            throw new InvalidArgumentException('Division durch 0 ist nicht erlaubt.');
        }

        return makeComplex($exponent['re'] == 0.0 ? 1.0 : 0.0, 0.0);
    }

    $modulus = hypot($base['re'], $base['im']);
    $argument = atan2($base['im'], $base['re']);
    $logReal = log($modulus);

    $expReal = $exponent['re'] * $logReal - $exponent['im'] * $argument;
    $expImag = $exponent['re'] * $argument + $exponent['im'] * $logReal;

    $scale = exp($expReal);
    return makeComplex($scale * cos($expImag), $scale * sin($expImag));
}

function formatScalar(float $value): string
{
    $value = normalizeNearZero($value);
    
    if ($value == 0.0) {
        return '0';
    }

    $absValue = abs($value);
    if ($absValue >= 1e9 || ($absValue < 1e-5 && $absValue > 0.0)) {
        return sprintf('%.10e', $value);
    }

    $formatted = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
}

function formatImaginaryTerm(float $imaginary, bool $forExpression): string
{
    $absImaginary = abs(normalizeNearZero($imaginary));
    if (abs($absImaginary - 1.0) < 1.0e-10) {
        return 'i';
    }

    $coefficient = formatScalar($absImaginary);
    return $forExpression ? $coefficient . '*i' : $coefficient . 'i';
}

function formatComplexForDisplay(array $value): string
{
    $real = normalizeNearZero($value['re']);
    $imaginary = normalizeNearZero($value['im']);

    if ($real == 0.0 && $imaginary == 0.0) {
        return '0';
    }

    if ($imaginary == 0.0) {
        return formatScalar($real);
    }

    if ($real == 0.0) {
        return ($imaginary < 0.0 ? '-' : '') . formatImaginaryTerm($imaginary, false);
    }

    return formatScalar($real)
        . ($imaginary < 0.0 ? '-' : '+')
        . formatImaginaryTerm($imaginary, false);
}

function formatComplexForExpression(array $value): string
{
    $real = normalizeNearZero($value['re']);
    $imaginary = normalizeNearZero($value['im']);

    if ($real == 0.0 && $imaginary == 0.0) {
        return '0';
    }

    if ($imaginary == 0.0) {
        return formatScalar($real);
    }

    if ($real == 0.0) {
        return ($imaginary < 0.0 ? '-' : '') . formatImaginaryTerm($imaginary, true);
    }

    return formatScalar($real)
        . ($imaginary < 0.0 ? '-' : '+')
        . formatImaginaryTerm($imaginary, true);
}

function isFiniteComplex(array $value): bool
{
    return is_finite($value['re']) && is_finite($value['im']);
}

function tokenize(string $expression): array
{
    $expression = trim(str_replace([' ', "\t", "\n", "\r", '√'], ['', '', '', '', 'sqrt'], $expression));
    if ($expression === '') {
        return [];
    }
    preg_match_all('/\d+(?:\.\d+)?|sqrt|root|i|[+\-*\/^(),]|./i', $expression, $matches);
    $tokens = $matches[0] ?? [];
    $normalized = [];
    foreach ($tokens as $token) {
        $token = strtolower($token);
        if (isNumberToken($token) || isOperatorToken($token) || in_array($token, ['(', ')', ',', 'i'], true) || isFunctionToken($token)) {
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
    $expectOperand = true;

    foreach ($tokens as $token) {
        if (isNumberToken($token) || $token === 'i') {
            $output[] = $token;
            $expectOperand = false;
            continue;
        }

        if (isFunctionToken($token)) {
            $stack[] = $token;
            $expectOperand = true;
            continue;
        }

        if ($token === ',') {
            if ($expectOperand) {
                throw new InvalidArgumentException('Trennzeichen ist an dieser Stelle nicht erlaubt.');
            }

            while (!empty($stack) && end($stack) !== '(') {
                $output[] = array_pop($stack);
            }
            if (empty($stack) || end($stack) !== '(') {
                throw new InvalidArgumentException('Trennzeichen ist an dieser Stelle nicht erlaubt.');
            }

            $expectOperand = true;
            continue;
        }

        if (isOperatorToken($token)) {
            if ($expectOperand) {
                if ($token === '-') {
                    $stack[] = 'neg';
                    continue;
                }

                if ($token === '+') {
                    continue;
                }

                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }

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
            $expectOperand = true;
            continue;
        }

        if ($token === '(') {
            $stack[] = $token;
            $expectOperand = true;
            continue;
        }

        if ($token === ')') {
            if ($expectOperand) {
                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }

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

            $expectOperand = false;
            continue;
        }

        throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
    }

    if ($expectOperand) {
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
function evaluateRpn(array $rpn): array
{
    $stack = [];
    foreach ($rpn as $token) {
        if (isNumberToken($token)) {
            $stack[] = makeComplex((float) $token, 0.0);
            continue;
        }

        if ($token === 'i') {
            $stack[] = makeComplex(0.0, 1.0);
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
                    $stack[] = complexAdd($a, $b);
                    break;
                case '-':
                    $stack[] = complexSub($a, $b);
                    break;
                case '*':
                    $stack[] = complexMul($a, $b);
                    break;
                case '/':
                    $stack[] = complexDiv($a, $b);
                    break;
                case '^':
                    $stack[] = complexPow($a, $b);
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
            $stack[] = complexSqrt($value);
            continue;
        }

        if ($token === 'root') {
            if (count($stack) < 2) {
                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }
            $value = array_pop($stack);
            $degree = array_pop($stack);
            if (abs($degree['im']) > 1.0e-10) {
                throw new InvalidArgumentException('Die Wurzel benötigt eine positive ganze Zahl als Grad.');
            }

            $degreeRounded = (int) round($degree['re']);
            if ($degreeRounded <= 0 || abs($degree['re'] - $degreeRounded) > 1.0e-9) {
                throw new InvalidArgumentException('Die Wurzel benötigt eine positive ganze Zahl als Grad.');
            }

            $stack[] = complexPow($value, makeComplex(1.0 / $degreeRounded, 0.0));
            continue;
        }

        if ($token === 'neg') {
            if (count($stack) < 1) {
                throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
            }

            $value = array_pop($stack);
            $stack[] = makeComplex(-$value['re'], -$value['im']);
            continue;
        }

        throw new InvalidArgumentException('Unbekannter Operator.');
    }
    if (count($stack) !== 1) {
        throw new InvalidArgumentException('Ungültige mathematische Eingabe.');
    }
    $result = array_pop($stack);
    if (!isFiniteComplex($result)) {
        throw new InvalidArgumentException('Das Ergebnis ist zu groß oder ungültig.');
    }
    return $result;
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
        $result = formatComplexForDisplay($value);
        $nextExpression = formatComplexForExpression($value);
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
