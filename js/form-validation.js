const form = document.getElementById('registration');
const submitBtn = document.querySelector('.submit-btn');
const profilePicInput = document.getElementById('profilepic');
const fileInputWrapper = document.querySelector('.file-input-wrapper');

const MAX_PROFILE_PIC_SIZE = 5 * 1024 * 1024; // 5MB

const getProfilePicError = (file) => {
  if (!file) return 'Bitte wählen Sie ein Profilbild aus';
  if (file.size > MAX_PROFILE_PIC_SIZE) return 'Datei darf nicht größer als 5MB sein';

  const hasImageMime = typeof file.type === 'string' && file.type.startsWith('image/');
  const hasImageExtension = /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(file.name || '');

  if (!hasImageMime || !hasImageExtension) {
    return 'Nur Bild-Dateien sind erlaubt';
  }

  return '';
};

const getProfilePicFilesError = (files) => {
  if (!files || files.length === 0) return 'Bitte wählen Sie ein Profilbild aus';
  if (files.length > 1) return 'Bitte nur eine Datei hochladen';
  return getProfilePicError(files[0]);
};

const updateProfilePicUi = (file) => {
  const fileNameDisplay = document.querySelector('.file-name');
  if (fileNameDisplay) {
    fileNameDisplay.textContent = file ? file.name : 'Keine Datei ausgewählt';
  }
  fileInputWrapper?.classList.toggle('has-file', !!file);
};

const setProfilePicErrorState = (hasError) => {
  fileInputWrapper?.classList.toggle('is-invalid', hasError);
};

// Validierungsfunktionen
const validations = {
  name: (value) => {
    if (!value.trim()) return 'Name ist erforderlich';
    if (value.trim().length < 2) return 'Name muss mindestens 2 Zeichen lang sein';
    return '';
  },
  email: (value) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!value) return 'E-Mail ist erforderlich';
    if (!emailRegex.test(value)) return 'Bitte geben Sie eine gültige E-Mail ein';
    return '';
  },
  password: (value) => {
    if (!value) return 'Passwort ist erforderlich';
    if (value.length < 8) return 'Passwort muss mindestens 8 Zeichen lang sein';
    return '';
  },
  passwordConfirm: (value) => {
    const password = document.getElementById('password').value;
    if (!value) return 'Passwortbestätigung ist erforderlich';
    if (value !== password) return 'Passwörter stimmen nicht überein';
    return '';
  },
  salutation: (value) => {
    if (!value) return 'Bitte wählen Sie eine Anrede';
    return '';
  },
  profilepic: (input) => {
    return getProfilePicFilesError(input?.files);
  },
  hobbies: () => {
    const checkedHobbies = form.querySelectorAll('input[name="hobbies"]:checked');
    if (checkedHobbies.length === 0) return 'Bitte wählen Sie mindestens ein Hobby';
    if (checkedHobbies.length > 3) return 'Maximal 3 Hobbies auswählbar';
    return '';
  },
  terms: (checked) => {
    if (!checked) return 'Sie müssen den AGB zustimmen';
    return '';
  }
};

// Fehler anzeigen
const showError = (fieldName, message) => {
  const errorElement = document.getElementById(`${fieldName}-error`);
  if (errorElement) {
    errorElement.textContent = message;
  }
};

// Validierungen durchführen (mit optional showError Parameter)
const validateField = (fieldName, showError_flag = true) => {
  let isValid;

  if (fieldName === 'profilepic') {
    const input = document.getElementById('profilepic');
    const error = validations.profilepic(input);
    if (showError_flag) showError('profilepic', error);
    else if (!error) showError('profilepic', '');
    setProfilePicErrorState(!!error);
    isValid = !error;
  } else if (fieldName === 'hobbies') {
    const error = validations.hobbies();
    if (showError_flag) showError('hobbies', error);
    isValid = !error;
  } else if (fieldName === 'terms') {
    const checkbox = document.getElementById('terms');
    const error = validations.terms(checkbox.checked);
    if (showError_flag) showError('terms', error);
    isValid = !error;
  } else if (fieldName === 'passwordConfirm') {
    const error = validations.passwordConfirm(document.getElementById('password-confirm').value);
    if (showError_flag) showError('password-confirm', error);
    else if (!error) showError('password-confirm', ''); // Fehlermeldung löschen wenn gültig
    isValid = !error;
  } else {
    const field = document.getElementById(fieldName);
    const error = validations[fieldName] ? validations[fieldName](field.value) : '';
    if (showError_flag) showError(fieldName, error);
    else if (!error) showError(fieldName, ''); // Fehlermeldung löschen wenn gültig
    isValid = !error;
  }

  return isValid;
};

// File input change + Drag-and-Drop
if (profilePicInput) {
  profilePicInput.addEventListener('change', (e) => {
    const selectedFiles = e.target.files;
    const selectedFile = selectedFiles?.[0] || null;
    const fileError = getProfilePicFilesError(selectedFiles);

    if (fileError) {
      updateProfilePicUi(null);
      showError('profilepic', fileError);
      setProfilePicErrorState(true);
      updateSubmitState();
      return;
    }

    updateProfilePicUi(selectedFile);
    validateField('profilepic');
    updateSubmitState();
  });

  if (fileInputWrapper) {
    const preventDefaults = (event) => {
      event.preventDefault();
      event.stopPropagation();
    };

    ['dragenter', 'dragover'].forEach((eventName) => {
      fileInputWrapper.addEventListener(eventName, (event) => {
        preventDefaults(event);
        fileInputWrapper.classList.add('is-dragover');
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      fileInputWrapper.addEventListener(eventName, (event) => {
        preventDefaults(event);
        fileInputWrapper.classList.remove('is-dragover');
      });
    });

    fileInputWrapper.addEventListener('drop', (event) => {
      const droppedFiles = event.dataTransfer?.files;
      const droppedFile = droppedFiles?.[0] || null;
      const dropFilesError = getProfilePicFilesError(droppedFiles);

      if (dropFilesError) {
        profilePicInput.value = '';
        updateProfilePicUi(null);
        showError('profilepic', dropFilesError);
        setProfilePicErrorState(true);
        updateSubmitState();
        return;
      }

      const dropError = getProfilePicError(droppedFile);
      if (dropError) {
        profilePicInput.value = '';
        updateProfilePicUi(null);
        showError('profilepic', dropError);
        setProfilePicErrorState(true);
        updateSubmitState();
        return;
      }

      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(droppedFile);
      profilePicInput.files = dataTransfer.files;

      updateProfilePicUi(droppedFile);
      showError('profilepic', '');
      setProfilePicErrorState(false);
      updateSubmitState();
    });
  }
}

// Real-time validation auf Felder
['name', 'email', 'password', 'salutation'].forEach(fieldName => {
  const field = document.getElementById(fieldName);
  if (field) {
    // Beim blur (Feld verlassen) - Fehler anzeigen
    field.addEventListener('blur', () => validateField(fieldName, true));
    // Beim input (Tippen) - nur validieren ohne Fehlermeldung anzuzeigen
    field.addEventListener('input', () => {
      validateField(fieldName, false);
      // Wenn Passwort geändert wird, auch Passwortbestätigung validieren (aber ohne Fehler zu zeigen)
      if (fieldName === 'password') {
        const confirmField = document.getElementById('password-confirm');
        if (confirmField && confirmField.value) {
          validateField('passwordConfirm', false);
        }
      }
      updateSubmitState();
    });
  }
});

// Passwort-Bestätigung Validierung
const passwordConfirmField = document.getElementById('password-confirm');
if (passwordConfirmField) {
  passwordConfirmField.addEventListener('blur', () => validateField('passwordConfirm', true));
  passwordConfirmField.addEventListener('input', () => {
    validateField('passwordConfirm', false);
    updateSubmitState();
  });
}

// Hobbies Validierung - max 3 auswählbar
document.querySelectorAll('input[name="hobbies"]').forEach(checkbox => {
  checkbox.addEventListener('change', (e) => {
    const checkedHobbies = document.querySelectorAll('input[name="hobbies"]:checked');

    if (checkedHobbies.length > 3) {
      e.target.checked = false;
      showError('hobbies', 'Maximal 3 Hobbies auswählbar');
    } else {
      validateField('hobbies');
    }
    updateSubmitState();
  });
});

// Beschreibung Validierung
document.getElementById('description')?.addEventListener('input', () => {
  const descriptionField = document.getElementById('description');
  const errorElement = document.getElementById('description-error');
  if (descriptionField && errorElement) {
    if (descriptionField.value.length > 500) {
      errorElement.textContent = 'Beschreibung darf maximal 500 Zeichen lang sein';
    } else {
      errorElement.textContent = '';
    }
  }
});

// Terms Validierung
document.getElementById('terms')?.addEventListener('change', () => {
  validateField('terms');
  updateSubmitState();
});

// Submit Button State Update
const updateSubmitState = () => {
  if (!submitBtn) return;

  const isNameValid = !validations.name(document.getElementById('name').value);
  const isEmailValid = !validations.email(document.getElementById('email').value);
  const isPasswordValid = !validations.password(document.getElementById('password').value);
  const isPasswordConfirmValid = !validations.passwordConfirm(document.getElementById('password-confirm').value);
  const isSalutationValid = !validations.salutation(document.getElementById('salutation').value);
  const isProfileValid = !validations.profilepic(document.getElementById('profilepic'));
  const isHobbiesValid = !validations.hobbies();
  const isTermsValid = !validations.terms(document.getElementById('terms').checked);

  const allValid = isNameValid && isEmailValid && isPasswordValid && isPasswordConfirmValid && isSalutationValid &&
                   isProfileValid && isHobbiesValid && isTermsValid;

  submitBtn.disabled = !allValid;
};

// Form Submit Handler
if (form && submitBtn) {
  form.addEventListener('submit', (e) => {
    // Alle Felder validieren
    const fieldsToValidate = ['name', 'email', 'password', 'passwordConfirm', 'salutation', 'profilepic', 'hobbies', 'terms'];
    let allValid = true;

    fieldsToValidate.forEach(field => {
      if (!validateField(field)) {
        allValid = false;
      }
    });

    if (!allValid) {
      // Nur bei Fehlern den nativen Submit stoppen.
      e.preventDefault();

    }
  });
}

// Initial state
updateSubmitState();
