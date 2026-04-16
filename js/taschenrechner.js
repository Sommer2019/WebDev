(() => {
  const expressionInput = document.getElementById('expressionInput');
  const expressionView = document.getElementById('expressionView');
  const resultView = document.querySelector('.result');
  const errorView = document.querySelector('.error');
  const form = document.getElementById('calcForm');
  const submitButton = document.querySelector('button[type="submit"]');

  if (!expressionInput || !expressionView || !form) {
    return;
  }

  let justCalculated = form.dataset.justCalculated === '1';
  let hasError = Boolean(errorView);

  const operators = new Set(['+', '-', '*', '/', '^']);
  const functionPrefixes = ['sqrt(', 'root('];
  const sqrtButton = document.querySelector('button[data-value="sqrt("]');
  const dotButton = document.querySelector('button[data-value="."]');
  const longPressDelayMs = 500;

  const updateExpressionView = () => {
    expressionView.textContent = expressionInput.value;
  };

  const resetResult = () => {
    if (resultView) {
      resultView.textContent = '0';
    }
  };

  const clearErrorState = () => {
    if (errorView) {
      errorView.remove();
    }

    if (resultView && resultView.textContent === 'Fehler') {
      resetResult();
    }

    hasError = false;
  };

  const startsWithFunction = (value) => functionPrefixes.some((prefix) => value.startsWith(prefix));
  const lastChar = () => expressionInput.value.trimEnd().slice(-1);

  const shouldInsertMultiplication = (value) => {
    const prev = lastChar();
    if (!prev) {
      return false;
    }

    if (value === '(' || startsWithFunction(value)) {
      return /[\d)]/.test(prev);
    }

    return false;
  };

  const startNewExpression = (value) => {
    expressionInput.value = value;
    justCalculated = false;
    clearErrorState();
    resetResult();
    updateExpressionView();
  };

  const appendValue = (value) => {
    if (hasError) {
      if (/^\d$/.test(value)) {
        startNewExpression(value);
        return;
      }

      if (value === '.') {
        expressionInput.value += '.';
        justCalculated = false;
        clearErrorState();
        updateExpressionView();
        return;
      }

      if (value === '(' || startsWithFunction(value)) {
        startNewExpression(value);
      }

      return;
    }

    if (justCalculated) {
      if (operators.has(value)) {
        expressionInput.value += value;
        justCalculated = false;
        clearErrorState();
        updateExpressionView();
        return;
      }

      if (/^\d$/.test(value)) {
        startNewExpression(value);
        return;
      }

      if (value === '.') {
        startNewExpression('0.');
        return;
      }

      if (value === '(' || startsWithFunction(value)) {
        expressionInput.value += `*${value}`;
        justCalculated = false;
        clearErrorState();
        updateExpressionView();
        return;
      }

      if (value === ')') {
        justCalculated = false;
        clearErrorState();
        updateExpressionView();
        return;
      }
    }

    if (value === '(' || startsWithFunction(value)) {
      if (shouldInsertMultiplication(value)) {
        expressionInput.value += '*';
      }

      expressionInput.value += value;
      clearErrorState();
      updateExpressionView();
      return;
    }

    expressionInput.value += value;
    clearErrorState();
    updateExpressionView();
  };

  const clearCalculation = () => {
    expressionInput.value = '';
    justCalculated = false;
    clearErrorState();
    resetResult();
    updateExpressionView();
  };

  const backspaceCalculation = () => {
    expressionInput.value = expressionInput.value.slice(0, -1);
    justCalculated = false;
    clearErrorState();
    resetResult();
    updateExpressionView();
  };

  document.querySelectorAll('button[data-value]').forEach((btn) => {
    if (btn === sqrtButton || btn === dotButton) {
      return;
    }

    btn.addEventListener('click', () => {
      appendValue(btn.dataset.value || '');
    });
  });

  if (sqrtButton) {
    let longPressTimer = null;
    let longPressTriggered = false;
    let ignoreClick = false;

    const clearLongPressTimer = () => {
      if (longPressTimer !== null) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }
    };

    const handlePressStart = (event) => {
      if (event.type === 'mousedown' && event.button !== 0) {
        return;
      }

      longPressTriggered = false;
      clearLongPressTimer();

      longPressTimer = setTimeout(() => {
        appendValue('root(');
        longPressTriggered = true;
        ignoreClick = true;
        longPressTimer = null;
      }, longPressDelayMs);
    };

    const handlePressEnd = () => {
      if (longPressTriggered) {
        clearLongPressTimer();
        return;
      }

      clearLongPressTimer();
      appendValue('sqrt(');
    };

    sqrtButton.addEventListener('mousedown', handlePressStart);
    sqrtButton.addEventListener('touchstart', handlePressStart, { passive: true });

    sqrtButton.addEventListener('mouseup', handlePressEnd);
    sqrtButton.addEventListener('touchend', handlePressEnd);

    sqrtButton.addEventListener('mouseleave', clearLongPressTimer);
    sqrtButton.addEventListener('touchcancel', clearLongPressTimer);

    // Browsers dispatch a click after pointer/touch release; suppress it after long-press.
    sqrtButton.addEventListener('click', (event) => {
      // Keyboard activation triggers click without a preceding pointer press.
      if (event.detail === 0) {
        appendValue('sqrt(');
        event.preventDefault();
        return;
      }

      event.preventDefault();
      if (ignoreClick) {
        ignoreClick = false;
      }
    });
  }

  if (dotButton) {
    let longPressTimer = null;
    let longPressTriggered = false;
    let ignoreClick = false;

    const clearLongPressTimer = () => {
      if (longPressTimer !== null) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }
    };

    const handlePressStart = (event) => {
      if (event.type === 'mousedown' && event.button !== 0) {
        return;
      }

      longPressTriggered = false;
      clearLongPressTimer();

      longPressTimer = setTimeout(() => {
        appendValue(',');
        longPressTriggered = true;
        ignoreClick = true;
        longPressTimer = null;
      }, longPressDelayMs);
    };

    const handlePressEnd = () => {
      if (longPressTriggered) {
        clearLongPressTimer();
        return;
      }

      clearLongPressTimer();
      appendValue('.');
    };

    dotButton.addEventListener('mousedown', handlePressStart);
    dotButton.addEventListener('touchstart', handlePressStart, { passive: true });

    dotButton.addEventListener('mouseup', handlePressEnd);
    dotButton.addEventListener('touchend', handlePressEnd);

    dotButton.addEventListener('mouseleave', clearLongPressTimer);
    dotButton.addEventListener('touchcancel', clearLongPressTimer);

    dotButton.addEventListener('click', (event) => {
      if (event.detail === 0) {
        appendValue('.');
        event.preventDefault();
        return;
      }

      event.preventDefault();
      if (ignoreClick) {
        ignoreClick = false;
      }
    });
  }

  document.querySelector('[data-action="clear"]')?.addEventListener('click', clearCalculation);
  document.querySelector('[data-action="backspace"]')?.addEventListener('click', backspaceCalculation);

  form.addEventListener('submit', () => {
    expressionInput.value = expressionInput.value.trim();
    justCalculated = false;
  });

  document.addEventListener('keydown', (event) => {
    const activeElement = document.activeElement;
    if (activeElement && ['INPUT', 'TEXTAREA'].includes(activeElement.tagName)) {
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitButton || undefined);
      } else {
        submitButton?.click();
      }
      return;
    }

    if (event.key === 'Backspace') {
      event.preventDefault();
      backspaceCalculation();
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      clearCalculation();
      return;
    }

    if (/^\d$/.test(event.key) || ['+', '-', '*', '/', '^', '(', ')', '.', ','].includes(event.key)) {
      event.preventDefault();
      appendValue(event.key);
    }
  });

  updateExpressionView();
})();

