/*
 * Dropdown Domisili yang bisa dicari (pola ARIA combobox + listbox).
 *
 * Progressive enhancement: <select data-combobox> tetap menjadi sumber data
 * dan nilai yang dikirim (regency_id). Jika skrip ini tidak berjalan, select
 * biasa tetap dipakai. Jika berjalan, select disembunyikan dan diganti kotak
 * input + daftar pilihan.
 *
 * Pencarian tidak peka huruf besar-kecil dan mengabaikan awalan "Kab." /
 * "Kabupaten" / "Kota": "semarang" menemukan Kota Semarang dan Kab. Semarang.
 * Mengetik awalannya ("kota teg") mempersempit ke jenis itu. Opsi bertanda
 * data-combobox-always ("Luar Jawa Tengah") selalu ada di akhir daftar.
 */
(function () {
  'use strict';

  var PREFIX = /^(kabupaten|kab|kota)\s+/;
  var MOBILE = '(max-width: 991px)';

  function normalize(text) {
    return text.toLowerCase().replace(/\./g, ' ').replace(/\s+/g, ' ').trim();
  }

  function parse(text) {
    var normalized = normalize(text);
    var match = normalized.match(PREFIX);

    return {
      prefix: match ? (match[1] === 'kota' ? 'kota' : 'kab') : '',
      base: match ? normalized.slice(match[0].length) : normalized,
    };
  }

  function enhance(select) {
    var field = select.closest('.field');
    var baseId = select.id;
    var listId = baseId + '-listbox';

    var items = Array.prototype.filter.call(select.options, function (option) {
      return option.value !== '';
    }).map(function (option) {
      var parsed = parse(option.text);

      return {
        value: option.value,
        label: option.text,
        full: normalize(option.text),
        prefix: parsed.prefix,
        base: parsed.base,
        always: option.hasAttribute('data-combobox-always'),
      };
    });

    var wrapper = document.createElement('div');
    wrapper.className = 'combobox';

    var input = document.createElement('input');
    input.type = 'text';
    input.id = baseId;
    input.className = 'combobox__input';
    input.placeholder = 'Ketik nama kabupaten/kota…';
    input.autocomplete = 'off';
    input.setAttribute('autocapitalize', 'none');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);

    var list = document.createElement('ul');
    list.id = listId;
    list.className = 'combobox__list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    // Label menunjuk ke input; select asli tetap dikirim tapi tidak lagi
    // terlihat atau bisa difokus.
    select.id = baseId + '-native';
    select.hidden = true;
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    var label = field ? field.querySelector('label[for="' + baseId + '"]') : null;

    if (label) {
      list.setAttribute('aria-label', label.textContent.trim());
    }

    var error = field ? field.querySelector('.field__error') : null;

    if (error) {
      error.id = error.id || baseId + '-error';
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', error.id);
    }

    wrapper.appendChild(input);
    wrapper.appendChild(list);
    select.parentNode.insertBefore(wrapper, select.nextSibling);

    var visible = [];
    var active = -1;

    function selectedItem() {
      for (var i = 0; i < items.length; i++) {
        if (items[i].value === select.value) {
          return items[i];
        }
      }

      return null;
    }

    function filter(query) {
      var q = parse(query);
      var fullQuery = normalize(query);

      function matches(item) {
        if (q.base === '' && q.prefix === '') {
          return true;
        }

        if (item.full.indexOf(fullQuery) !== -1) {
          return true;
        }

        return (q.prefix === '' || q.prefix === item.prefix) && item.base.indexOf(q.base) !== -1;
      }

      var always = items.filter(function (item) { return item.always; });

      return {
        found: items.filter(function (item) { return !item.always && matches(item); }),
        always: always,
        // "Tidak ditemukan" hanya jika tidak ada satu pun yang cocok,
        // termasuk "Luar Jawa Tengah" (yang tetap ditampilkan di akhir).
        empty: !items.some(matches),
      };
    }

    function optionId(item) {
      return baseId + '-option-' + item.value;
    }

    function setActive(index) {
      var options = list.querySelectorAll('[role="option"]');

      if (active >= 0 && options[active]) {
        options[active].classList.remove('is-active');
      }

      active = index;

      if (active >= 0 && options[active]) {
        options[active].classList.add('is-active');
        input.setAttribute('aria-activedescendant', options[active].id);
        options[active].scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function render(query) {
      var result = filter(query);
      var current = select.value;

      list.innerHTML = '';
      visible = result.found.concat(result.always);

      if (result.empty) {
        var empty = document.createElement('li');
        empty.className = 'combobox__empty';
        empty.setAttribute('role', 'presentation');
        empty.textContent = 'Tidak ditemukan';
        list.appendChild(empty);
      }

      visible.forEach(function (item) {
        var li = document.createElement('li');
        li.id = optionId(item);
        li.className = 'combobox__option';
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', item.value === current ? 'true' : 'false');
        li.setAttribute('data-value', item.value);
        li.textContent = item.label;
        list.appendChild(li);
      });

      active = -1;
      input.removeAttribute('aria-activedescendant');
    }

    function open() {
      if (!list.hidden) {
        return;
      }

      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      setActive(-1);
    }

    function choose(item) {
      select.value = item ? item.value : '';
      input.value = item ? item.label : '';
      select.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    }

    // Keluar dari field: teks yang tidak cocok persis dengan pilihan
    // dikosongkan, supaya validasi server menangkapnya.
    function commit() {
      var typed = normalize(input.value);
      var current = selectedItem();

      if (current && normalize(current.label) === typed) {
        input.value = current.label;
        close();

        return;
      }

      var exact = null;

      for (var i = 0; i < items.length; i++) {
        if (items[i].full === typed) {
          exact = items[i];
        }
      }

      choose(exact);
    }

    input.addEventListener('focus', function () {
      // Saat fokus tampilkan seluruh daftar, pilihan saat ini tersorot.
      render('');
      open();

      var current = selectedItem();

      if (current) {
        setActive(visible.indexOf(current));
      }

      if (window.matchMedia && window.matchMedia(MOBILE).matches) {
        // Tunggu keyboard layar muncul, lalu gulir field ke atas layar.
        window.setTimeout(function () {
          (field || wrapper).scrollIntoView({ block: 'start', behavior: 'smooth' });
        }, 300);
      }
    });

    input.addEventListener('input', function () {
      render(input.value);
      open();

      if (visible.length > 0 && normalize(input.value) !== '') {
        setActive(0);
      }
    });

    input.addEventListener('keydown', function (event) {
      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();

          if (list.hidden) {
            render(input.value);
            open();
          }

          setActive(Math.min(active + 1, visible.length - 1));
          break;

        case 'ArrowUp':
          event.preventDefault();

          if (!list.hidden) {
            setActive(Math.max(active - 1, 0));
          }
          break;

        case 'Enter':
          // Enter saat daftar terbuka memilih, bukan mengirim form.
          if (!list.hidden) {
            event.preventDefault();

            if (active >= 0 && visible[active]) {
              choose(visible[active]);
            }
          }
          break;

        case 'Escape':
          if (!list.hidden) {
            event.preventDefault();
            close();
          }
          break;
      }
    });

    input.addEventListener('blur', commit);

    // pointerdown/mousedown dicegah supaya input tidak kehilangan fokus
    // (dan menjalankan commit) sebelum klik pada pilihan diproses.
    list.addEventListener('mousedown', function (event) {
      event.preventDefault();
    });

    list.addEventListener('click', function (event) {
      var li = event.target.closest('[role="option"]');

      if (!li) {
        return;
      }

      var value = li.getAttribute('data-value');

      for (var i = 0; i < items.length; i++) {
        if (items[i].value === value) {
          choose(items[i]);
        }
      }
    });

    // Nilai lama (old('regency_id') setelah error validasi) langsung tampil.
    var initial = selectedItem();

    if (initial) {
      input.value = initial.label;
    }
  }

  Array.prototype.forEach.call(document.querySelectorAll('select[data-combobox]'), enhance);
})();
