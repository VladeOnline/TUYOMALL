(function () {
  const AVATAR_KEY = 'tuyomall_business_avatar_v1';
  const avatarSelector = '[data-business-avatar]';
  const inputSelector = '[data-business-avatar-input]';
  const buttonSelector = '[data-business-avatar-button]';

  function applyAvatar(value) {
    if (!value) return;
    document.querySelectorAll(avatarSelector).forEach((img) => {
      img.src = value;
      img.classList.remove('is-empty');
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
    bindAvatarUpload();
  }

  window.TuyoMallBusinessProfile = {
    avatarKey: AVATAR_KEY,
    applyAvatar
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBusinessProfile);
  } else {
    initBusinessProfile();
  }

})();
