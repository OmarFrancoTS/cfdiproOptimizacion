(function () {
  'use strict';

  var ReportesUI = {
    themeKey: 'ui-theme',

    applyInitialTheme: function () {
      var root = document.documentElement;
      var saved = null;

      try {
        saved = localStorage.getItem(this.themeKey);
      } catch (e) {}

      if (saved === 'light' || saved === 'dark') {
        root.setAttribute('data-theme', saved);
        return saved;
      }

      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        root.setAttribute('data-theme', 'dark');
        return 'dark';
      }

      root.setAttribute('data-theme', 'light');
      return 'light';
    },

    getCurrentTheme: function () {
      return document.documentElement.getAttribute('data-theme') || 'light';
    },

    setTheme: function (theme) {
      var root = document.documentElement;
      var next = (theme === 'dark') ? 'dark' : 'light';

      root.setAttribute('data-theme', next);

      try {
        localStorage.setItem(this.themeKey, next);
      } catch (e) {}

      return next;
    },

    syncThemeButton: function (buttonId) {
      var btn = document.getElementById(buttonId || 'themeToggle');
      if (!btn) return;

      var dark = this.getCurrentTheme() === 'dark';
      var sun = btn.querySelector('.sun');
      var moon = btn.querySelector('.moon');

      if (sun) sun.style.display = dark ? 'none' : 'inline';
      if (moon) moon.style.display = dark ? 'inline' : 'none';
    },

    initTheme: function (buttonId) {
      this.applyInitialTheme();

      var btn = document.getElementById(buttonId || 'themeToggle');
      this.syncThemeButton(buttonId || 'themeToggle');

      if (!btn) return;

      var self = this;

      if (btn.dataset.themeBound === '1') return;
      btn.dataset.themeBound = '1';

      btn.addEventListener('click', function () {
        var current = self.getCurrentTheme();
        var next = current === 'light' ? 'dark' : 'light';
        self.setTheme(next);
        self.syncThemeButton(buttonId || 'themeToggle');
      });
    },

    hideThemeInIframe: function (buttonId) {
      if (window.self !== window.top) {
        var btn = document.getElementById(buttonId || 'themeToggle');
        if (btn) btn.style.display = 'none';
      }
    },

    initThemeAuto: function (buttonId) {
      this.initTheme(buttonId || 'themeToggle');
      this.hideThemeInIframe(buttonId || 'themeToggle');
    },

    debounce: function (fn, wait) {
      var timeout = null;
      wait = parseInt(wait || 250, 10);

      return function () {
        var context = this;
        var args = arguments;

        clearTimeout(timeout);
        timeout = setTimeout(function () {
          fn.apply(context, args);
        }, wait);
      };
    },

    filterTable: function (inputId, tableId, options) {
      var input = document.getElementById(inputId);
      var table = document.getElementById(tableId);
      if (!input || !table) return;

      options = options || {};

      var tbody = table.querySelector('tbody');
      if (!tbody) return;

      var rowSelector = options.rowSelector || 'tr';
      var rows = Array.prototype.slice.call(tbody.querySelectorAll(rowSelector));
      var minChars = parseInt(options.minChars || 0, 10);
      var callback = typeof options.onAfterFilter === 'function' ? options.onAfterFilter : null;

      function normalize(value) {
        return String(value || '')
          .toLowerCase()
          .replace(/\s+/g, ' ')
          .trim();
      }

      function runFilter() {
        var val = normalize(input.value);

        if (val.length < minChars) {
          for (var i = 0; i < rows.length; i++) {
            rows[i].style.display = '';
          }
          if (callback) callback(rows, val);
          return;
        }

        for (var j = 0; j < rows.length; j++) {
          var txt = normalize(rows[j].innerText || rows[j].textContent || '');
          rows[j].style.display = txt.indexOf(val) !== -1 ? '' : 'none';
        }

        if (callback) callback(rows, val);
      }

      var handler = runFilter;
      if (options.debounce) {
        handler = this.debounce(runFilter, options.debounce);
      }

      if (input.dataset.filterBound !== '1') {
        input.addEventListener('keyup', handler);
        input.addEventListener('search', handler);
        input.dataset.filterBound = '1';
      }

      runFilter();
    },

    filterGroupedTable: function (inputId, tableId, options) {
      var input = document.getElementById(inputId);
      var table = document.getElementById(tableId);
      if (!input || !table) return;

      options = options || {};

      var tbody = table.querySelector('tbody');
      if (!tbody) return;

      var groupAttr = options.groupAttr || 'data-group';
      var callback = typeof options.onAfterFilter === 'function' ? options.onAfterFilter : null;

      function normalize(value) {
        return String(value || '')
          .toLowerCase()
          .replace(/\s+/g, ' ')
          .trim();
      }

      function runFilter() {
        var val = normalize(input.value);
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var groups = {};

        for (var i = 0; i < rows.length; i++) {
          var groupKey = rows[i].getAttribute(groupAttr) || ('__row_' + i);
          if (!groups[groupKey]) groups[groupKey] = [];
          groups[groupKey].push(rows[i]);
        }

        for (var key in groups) {
          if (!groups.hasOwnProperty(key)) continue;

          var groupRows = groups[key];
          var groupText = '';

          for (var j = 0; j < groupRows.length; j++) {
            groupText += ' ' + normalize(groupRows[j].innerText || groupRows[j].textContent || '');
          }

          var visible = (val === '') || (groupText.indexOf(val) !== -1);

          for (var k = 0; k < groupRows.length; k++) {
            groupRows[k].style.display = visible ? '' : 'none';
          }
        }

        if (callback) callback(rows, val);
      }

      var handler = runFilter;
      if (options.debounce) {
        handler = this.debounce(runFilter, options.debounce);
      }

      if (input.dataset.groupFilterBound !== '1') {
        input.addEventListener('keyup', handler);
        input.addEventListener('search', handler);
        input.dataset.groupFilterBound = '1';
      }

      runFilter();
    },

    paginateTable: function (tableId, paginationId, perPage) {
      var table = document.getElementById(tableId);
      var pag = document.getElementById(paginationId);
      if (!table || !pag) return;

      perPage = parseInt(perPage || 10, 10);

      var tbody = table.querySelector('tbody');
      if (!tbody) return;

      var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
      if (!allRows.length) {
        pag.innerHTML = '';
        return;
      }

      var currentPage = 1;

      function getVisibleRows() {
        var rows = [];
        for (var i = 0; i < allRows.length; i++) {
          if (allRows[i].style.display !== 'none') {
            rows.push(allRows[i]);
          }
        }
        return rows;
      }

      function hideAllRows() {
        for (var i = 0; i < allRows.length; i++) {
          allRows[i].style.display = 'none';
        }
      }

      function renderRows() {
        var visibleRows = getVisibleRows();

        if (visibleRows.length <= perPage) {
          pag.innerHTML = '';
          for (var i = 0; i < visibleRows.length; i++) {
            visibleRows[i].style.display = '';
          }
          return;
        }

        var totalPages = Math.ceil(visibleRows.length / perPage);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        hideAllRows();

        var start = (currentPage - 1) * perPage;
        var end = start + perPage;

        for (var j = start; j < end && j < visibleRows.length; j++) {
          visibleRows[j].style.display = '';
        }

        buildPagination(totalPages);
      }

      function buildPagination(totalPages) {
        pag.innerHTML = '';

        function addBtn(label, page, active, disabled, ghost) {
          var el = document.createElement('button');
          el.type = 'button';
          el.textContent = label;

          if (active) el.className = 'active';
          if (ghost) el.className = 'ghost';
          if (disabled) el.disabled = true;

          if (!disabled && !ghost) {
            el.addEventListener('click', function () {
              currentPage = page;
              renderRows();
            });
          }

          pag.appendChild(el);
        }

        var windowSize = 2;
        var start = Math.max(1, currentPage - windowSize);
        var end = Math.min(totalPages, currentPage + windowSize);

        if (currentPage > 1) {
          addBtn('« Primera', 1, false, false, false);
          addBtn('‹ Anterior', currentPage - 1, false, false, false);
        }

        addBtn('1', 1, currentPage === 1, false, false);

        if (start > 2) {
          addBtn('…', 0, false, true, true);
        }

        for (var i = start; i <= end; i++) {
          if (i === 1 || i === totalPages) continue;
          addBtn(String(i), i, currentPage === i, false, false);
        }

        if (end < totalPages - 1) {
          addBtn('…', 0, false, true, true);
        }

        if (totalPages > 1) {
          addBtn(String(totalPages), totalPages, currentPage === totalPages, false, false);
        }

        if (currentPage < totalPages) {
          addBtn('Siguiente ›', currentPage + 1, false, false, false);
          addBtn('Última »', totalPages, false, false, false);
        }
      }

      var api = {
        refresh: function () {
          currentPage = 1;
          renderRows();
        },
        goToPage: function (page) {
          currentPage = parseInt(page || 1, 10);
          renderRows();
        }
      };

      renderRows();
      return api;
    },

    quickCheckAll: function (masterSelector, itemSelector) {
      var master = document.querySelector(masterSelector);
      if (!master) return;

      if (master.dataset.quickCheckBound === '1') return;
      master.dataset.quickCheckBound = '1';

      master.addEventListener('change', function () {
        var items = document.querySelectorAll(itemSelector);
        for (var i = 0; i < items.length; i++) {
          items[i].checked = master.checked;
        }
      });
    },

    serializeForm: function (form) {
      if (!form) return '';

      var parts = [];
      var elements = form.querySelectorAll('input, select, textarea');

      for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.disabled) continue;

        var type = (el.type || '').toLowerCase();

        if ((type === 'checkbox' || type === 'radio') && !el.checked) {
          continue;
        }

        parts.push(
          encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value)
        );
      }

      return parts.join('&');
    },

    buildQueryString: function (params) {
      if (!params) return '';

      var parts = [];
      for (var key in params) {
        if (!params.hasOwnProperty(key)) continue;
        if (params[key] === null || typeof params[key] === 'undefined') continue;

        parts.push(
          encodeURIComponent(key) + '=' + encodeURIComponent(params[key])
        );
      }
      return parts.join('&');
    },

    updateUrlParams: function (params, replaceState) {
      if (!window.history || !window.location) return;

      var url = new URL(window.location.href);

      for (var key in params) {
        if (!params.hasOwnProperty(key)) continue;

        var value = params[key];
        if (value === null || typeof value === 'undefined' || value === '') {
          url.searchParams.delete(key);
        } else {
          url.searchParams.set(key, value);
        }
      }

      if (replaceState) {
        window.history.replaceState({}, '', url.toString());
      } else {
        window.history.pushState({}, '', url.toString());
      }
    },

    getParam: function (name) {
      if (!name || !window.location) return null;
      var url = new URL(window.location.href);
      return url.searchParams.get(name);
    },

    initServerSearchForm: function (formId, options) {
      var form = document.getElementById(formId);
      if (!form) return;

      options = options || {};
      var resetPageFieldName = options.resetPageFieldName || 'page';

      if (form.dataset.serverSearchBound === '1') return;
      form.dataset.serverSearchBound = '1';

      form.addEventListener('submit', function () {
        var pageField = form.querySelector('[name="' + resetPageFieldName + '"]');
        if (pageField) {
          pageField.value = '1';
        }
      });
    }, 
    filterSelect: function (input, selectId) {
      var filter = String(input.value || '').toLowerCase();
      var select = document.getElementById(selectId);
      if (!select) return;

      var options = select.options;
      var firstVisible = -1;

      for (var i = 0; i < options.length; i++) {
        var txt = String(options[i].text || '').toLowerCase();
        var visible = txt.indexOf(filter) !== -1;

        options[i].style.display = visible ? '' : 'none';

        if (visible && firstVisible === -1 && options[i].value !== '0') {
          firstVisible = i;
        }
      }

      if (firstVisible >= 0) {
        select.selectedIndex = firstVisible;
      }
    }
    
  };

  window.ReportesUI = ReportesUI;
})();