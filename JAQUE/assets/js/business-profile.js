(function () {
  const AVATAR_KEY = 'tuyomall_business_avatar_v1';
  const avatarSelector = '[data-business-avatar]';
  const inputSelector = '[data-business-avatar-input]';
  const buttonSelector = '[data-business-avatar-button]';

  function storedAvatar() {
    try {
      return localStorage.getItem(AVATAR_KEY);
    } catch (_) {
      return null;
    }
  }

  function setStoredAvatar(value) {
    try {
      localStorage.setItem(AVATAR_KEY, value);
    } catch (_) {}
  }

  function applyAvatar(value) {
    if (!value) return;
    document.querySelectorAll(avatarSelector).forEach((img) => {
      img.src = value;
    });
  }

  function bindAvatarUpload() {
    const input = document.querySelector(inputSelector);
    const button = document.querySelector(buttonSelector);
    if (!input || !button) return;

    button.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file || !file.type.startsWith('image/')) return;

      const reader = new FileReader();
      reader.onload = () => {
        const result = reader.result;
        if (typeof result !== 'string') return;
        resizeAvatar(result, (resized) => {
          setStoredAvatar(resized);
          applyAvatar(resized);
        });
      };
      reader.readAsDataURL(file);
    });
  }

  function resizeAvatar(dataUrl, done) {
    const img = new Image();
    img.onload = () => {
      const size = 520;
      const side = Math.min(img.width, img.height);
      const sx = Math.max(0, (img.width - side) / 2);
      const sy = Math.max(0, (img.height - side) / 2);
      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, sx, sy, side, side, 0, 0, size, size);
      done(canvas.toDataURL('image/jpeg', 0.86));
    };
    img.onerror = () => done(dataUrl);
    img.src = dataUrl;
  }

  function initBusinessProfile() {
    applyAvatar(storedAvatar());
    bindAvatarUpload();
  }

  window.TuyoMallBusinessProfile = {
    avatarKey: AVATAR_KEY,
    getAvatar: storedAvatar,
    applyAvatar
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBusinessProfile);
  } else {
    initBusinessProfile();
  }

  window.addEventListener('storage', (event) => {
    if (event.key === AVATAR_KEY) applyAvatar(event.newValue);
  });
})();
