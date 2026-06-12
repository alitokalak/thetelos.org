/**
 * Firestore Book Browser — admin UI
 * Subjects → Authors → Works akışı, taslak içe aktarma.
 */
(function () {
    'use strict';

    if (typeof tfbData === 'undefined') return;

    var $ = function (id) { return document.getElementById(id); };

    function api(action, params) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', tfbData.nonce);
        Object.keys(params || {}).forEach(function (k) {
            if (params[k] !== null && params[k] !== undefined) body.set(k, params[k]);
        });
        return fetch(tfbData.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function showError(msg) {
        var box = $('tfb-error');
        if (!box) { window.alert(msg); return; }
        box.style.display = msg ? 'block' : 'none';
        box.querySelector('p').textContent = msg || '';
    }

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, ms);
        };
    }

    // ---------------------------------------------------
    // Settings sekmesi: şema önizleme
    // ---------------------------------------------------
    var previewBtn = $('tfb-preview-btn');
    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            var out = $('tfb-preview-output');
            out.style.display = 'block';
            out.textContent = 'Fetching…';
            api('thetelos_fb_preview', { collection: $('tfb-preview-collection').value.trim() })
                .then(function (res) {
                    out.textContent = res.success
                        ? (res.data.length ? JSON.stringify(res.data, null, 2) : 'Connection OK but collection is empty.')
                        : 'Error: ' + res.data;
                })
                .catch(function (e) { out.textContent = 'Request failed: ' + e; });
        });
    }

    // ---------------------------------------------------
    // Browser sekmesi
    // ---------------------------------------------------
    var subjectList = $('tfb-subject-list');
    if (!subjectList) return; // settings sekmesindeyiz

    var state = {
        subject: null,
        author: null,
        subjectsOffset: 0,
        authorsOffset: 0,
        worksOffset: 0,
        importing: false
    };

    var authorList = $('tfb-author-list');
    var workList   = $('tfb-work-list');

    function setLoading(ul) {
        ul.innerHTML = '<li class="tfb-muted">Loading…</li>';
    }

    function emptyRow(ul, text) {
        ul.innerHTML = '<li class="tfb-muted">' + esc(text) + '</li>';
    }

    // ---- Subjects ----
    function loadSubjects(append) {
        if (!append) { state.subjectsOffset = 0; setLoading(subjectList); }
        showError('');

        api('thetelos_fb_subjects', {
            q: $('tfb-subject-search').value.trim(),
            offset: state.subjectsOffset
        }).then(function (res) {
            if (!res.success) {
                emptyRow(subjectList, 'Subjects unavailable — type a subject and press Enter to filter directly.');
                showError(res.data);
                return;
            }
            if (!append) subjectList.innerHTML = '';
            if (!res.data.items.length && !append) {
                emptyRow(subjectList, 'No subjects found.');
            }
            res.data.items.forEach(function (it) {
                var li = document.createElement('li');
                li.className = 'tfb-item';
                li.innerHTML = esc(it.name);
                li.addEventListener('click', function () { pickSubject(it.name, li); });
                subjectList.appendChild(li);
            });
            state.subjectsOffset += res.data.items.length;
            $('tfb-subjects-more').style.display = res.data.has_more ? '' : 'none';
        }).catch(function (e) { showError('Request failed: ' + e); });
    }

    function pickSubject(name, li) {
        state.subject = name;
        state.author = null;
        subjectList.querySelectorAll('.tfb-active').forEach(function (el) { el.classList.remove('tfb-active'); });
        if (li) li.classList.add('tfb-active');
        $('tfb-author-context').textContent = 'in “' + name + '”';
        $('tfb-work-context').textContent = '';
        workList.innerHTML = '';
        $('tfb-works-more').style.display = 'none';
        loadAuthors(false);
    }

    // ---- Authors ----
    function loadAuthors(append) {
        if (!append) { state.authorsOffset = 0; setLoading(authorList); }
        showError('');

        api('thetelos_fb_authors', {
            subject: state.subject || '',
            q: $('tfb-author-search').value.trim(),
            offset: state.authorsOffset
        }).then(function (res) {
            if (!res.success) { emptyRow(authorList, 'Error.'); showError(res.data); return; }
            if (!append) authorList.innerHTML = '';
            if (!res.data.items.length && !append) emptyRow(authorList, 'No authors found.');

            if (res.data.via === 'works' && !append) {
                var note = document.createElement('li');
                note.className = 'tfb-muted tfb-note';
                note.textContent = 'Aggregated from works in this subject.';
                authorList.appendChild(note);
            }

            res.data.items.forEach(function (it) {
                var li = document.createElement('li');
                li.className = 'tfb-item';
                li.innerHTML = esc(it.name);
                li.addEventListener('click', function () { pickAuthor(it.name, li); });
                authorList.appendChild(li);
            });
            state.authorsOffset += res.data.items.length;
            $('tfb-authors-more').style.display = res.data.has_more ? '' : 'none';
        }).catch(function (e) { showError('Request failed: ' + e); });
    }

    function pickAuthor(name, li) {
        state.author = name;
        authorList.querySelectorAll('.tfb-active').forEach(function (el) { el.classList.remove('tfb-active'); });
        if (li) li.classList.add('tfb-active');
        $('tfb-work-context').textContent = 'by ' + name + (state.subject ? ' · ' + state.subject : '');
        loadWorks(false);
    }

    // ---- Works ----
    function loadWorks(append) {
        if (!state.author) return;
        if (!append) { state.worksOffset = 0; setLoading(workList); }
        showError('');

        api('thetelos_fb_works', {
            author: state.author,
            subject: state.subject || '',
            offset: state.worksOffset
        }).then(function (res) {
            if (!res.success) { emptyRow(workList, 'Error.'); showError(res.data); return; }
            if (!append) workList.innerHTML = '';
            if (!res.data.items.length && !append) emptyRow(workList, 'No works found for this author.');

            res.data.items.forEach(function (it) {
                workList.appendChild(workRow(it));
            });
            state.worksOffset += res.data.items.length;
            $('tfb-works-more').style.display = res.data.has_more ? '' : 'none';
            updateSelCount();
        }).catch(function (e) { showError('Request failed: ' + e); });
    }

    function workRow(it) {
        var li = document.createElement('li');
        li.className = 'tfb-item tfb-work' + (it.exists ? ' tfb-exists' : '');
        li.dataset.title = it.title;
        li.dataset.workId = it.id;

        var subjects = (it.subjects || []).map(function (s) {
            return '<span class="tfb-badge">' + esc(s) + '</span>';
        }).join('');

        li.innerHTML =
            '<label class="tfb-work-main">' +
                '<input type="checkbox" class="tfb-check"' + (it.exists ? ' disabled' : '') + '>' +
                '<span class="tfb-work-title">' + esc(it.title) + '</span>' +
            '</label>' +
            '<span class="tfb-work-meta">' + subjects + '</span>' +
            '<span class="tfb-work-actions">' +
                (it.exists
                    ? '<span class="tfb-imported">already on site</span>'
                    : '<button type="button" class="button button-small tfb-import-one">Import draft</button>') +
            '</span>';

        var checkbox = li.querySelector('.tfb-check');
        if (checkbox) checkbox.addEventListener('change', updateSelCount);

        var btn = li.querySelector('.tfb-import-one');
        if (btn) {
            btn.addEventListener('click', function () { importWork(li).catch(function () {}); });
        }
        return li;
    }

    // ---- Import ----
    function importWork(li) {
        var btn = li.querySelector('.tfb-import-one');
        var actions = li.querySelector('.tfb-work-actions');
        if (btn) { btn.disabled = true; btn.textContent = 'Importing…'; }

        return api('thetelos_fb_import', {
            title: li.dataset.title,
            author: state.author || '',
            work_id: li.dataset.workId,
            category_id: $('tfb-wp-category') ? $('tfb-wp-category').value : 0
        }).then(function (res) {
            if (!res.success) {
                if (btn) { btn.disabled = false; btn.textContent = 'Import draft'; }
                showError(res.data);
                throw new Error(res.data);
            }
            li.classList.add('tfb-exists');
            var check = li.querySelector('.tfb-check');
            if (check) { check.checked = false; check.disabled = true; }
            actions.innerHTML = '<a href="' + res.data.edit_link + '" target="_blank" class="tfb-imported">' +
                (res.data.duplicate ? 'already on site ↗' : 'draft created ↗') + '</a>';
            updateSelCount();
        });
    }

    function updateSelCount() {
        var checked = workList.querySelectorAll('.tfb-check:checked').length;
        $('tfb-sel-count').textContent = checked;
        $('tfb-import-selected').disabled = checked === 0 || state.importing;
    }

    $('tfb-select-all').addEventListener('change', function () {
        var on = this.checked;
        workList.querySelectorAll('.tfb-check:not(:disabled)').forEach(function (c) { c.checked = on; });
        updateSelCount();
    });

    $('tfb-import-selected').addEventListener('click', function () {
        if (state.importing) return;
        var rows = Array.prototype.slice.call(workList.querySelectorAll('.tfb-work'))
            .filter(function (li) {
                var c = li.querySelector('.tfb-check');
                return c && c.checked && !c.disabled;
            });
        if (!rows.length) return;

        state.importing = true;
        var status = $('tfb-toolbar-status');
        var done = 0;

        // Sunucuyu boğmamak için sıralı içe aktar
        function next() {
            if (!rows.length) {
                state.importing = false;
                status.textContent = 'Done — ' + done + ' imported.';
                updateSelCount();
                return;
            }
            var li = rows.shift();
            status.textContent = 'Importing ' + (done + 1) + '…';
            importWork(li)
                .then(function () { done++; })
                .catch(function () {})
                .then(next);
        }
        next();
        updateSelCount();
    });

    // ---- Aramalar ----
    var subjectSearch = $('tfb-subject-search');
    subjectSearch.addEventListener('input', debounce(function () { loadSubjects(false); }, 350));
    subjectSearch.addEventListener('keydown', function (e) {
        // Enter: yazılan değeri doğrudan subject filtresi olarak kullan
        // (subjects koleksiyonu olmasa da çalışır)
        if (e.key === 'Enter' && this.value.trim()) {
            e.preventDefault();
            pickSubject(this.value.trim(), null);
        }
    });

    $('tfb-author-search').addEventListener('input', debounce(function () {
        if (this.value.trim()) {
            // İsimle arama subject filtresinden bağımsız çalışır
            state.subject = null;
            subjectList.querySelectorAll('.tfb-active').forEach(function (el) { el.classList.remove('tfb-active'); });
            $('tfb-author-context').textContent = '';
        }
        loadAuthors(false);
    }, 350));

    $('tfb-subjects-more').addEventListener('click', function () { loadSubjects(true); });
    $('tfb-authors-more').addEventListener('click', function () { loadAuthors(true); });
    $('tfb-works-more').addEventListener('click', function () { loadWorks(true); });

    // İlk yükleme
    loadSubjects(false);
})();
