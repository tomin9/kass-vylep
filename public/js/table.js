(function ($) {
    'use strict';

    var KP     = window.KASSPub;
    var CENNIK = KP.cennik;
    var ORGS   = KP.orgs; // [{id, nazov}, …]

    /* ===== HELPERS ===== */
    function f2(n) { n = parseFloat(n) || 0; return n > 0 ? n.toFixed(2).replace('.', ',') + ' €' : '—'; }
    function parseNum(s) { return parseFloat(String(s || '').replace(',', '.')) || 0; }
    function todayISO() { return new Date().toISOString().slice(0, 10); }
    function addDays(d, days) { var dt = new Date(d); dt.setDate(dt.getDate() + days); return dt.toISOString().slice(0, 10); }
    function nextTuesday(d) { var dt = new Date(d), dow = dt.getDay(), diff = (2 - dow + 7) % 7; dt.setDate(dt.getDate() + diff); return dt.toISOString().slice(0, 10); }

    function status(msg, ok) {
        var $s = $('#kp-status');
        $s.text(msg).removeClass('kp-ok kp-err').addClass(ok ? 'kp-ok' : 'kp-err');
        clearTimeout($s.data('t'));
        $s.data('t', setTimeout(function () { $s.text(''); }, 3000));
    }

    /* ===== PLATBA SKRATKY ===== */
    var PLATBA_TO_SKR  = { 'Faktúra': 'FA', 'Zadarmo': 'Z', 'Hotovosť': 'H', 'Karta': 'K' };
    var PLATBA_OPTS = [
        { val: 'FA', full: 'Faktúra' },
        { val: 'Z',  full: 'Zadarmo' },
        { val: 'H',  full: 'Hotovosť' },
        { val: 'K',  full: 'Karta' },
    ];

    function platbaDropHtml(selectedFull) {
        var skr = PLATBA_TO_SKR[selectedFull] || 'Z';
        var opts = PLATBA_OPTS.map(function(o) {
            return '<div class="kp-platba-opt" data-val="' + o.val + '" data-full="' + o.full + '">'
                 + '<span class="kp-platba-skr">' + o.val + '</span>' + o.full + '</div>';
        }).join('');
        return '<div class="kp-platba-wrap">'
            + '<div class="kp-platba-btn" data-val="' + skr + '" data-full="' + selectedFull + '">' + skr + '</div>'
            + '<input type="hidden" class="kp-platba-hidden" name="platba" value="' + selectedFull + '">'
            + '<div class="kp-platba-drop">' + opts + '</div>'
            + '</div>';
    }
    function fmtDropHtml(selected) {
        selected = selected || 'A3';
        var opts = ['A4','A3','A2','A1'].map(function(f) {
            return '<div class="kp-fmt-opt" data-val="' + f + '">' + f + '</div>';
        }).join('');
        return '<div class="kp-fmt-wrap">'
            + '<div class="kp-fmt-btn">' + selected + '</div>'
            + '<input type="hidden" class="kp-format" name="format" value="' + selected + '">'
            + '<div class="kp-fmt-drop">' + opts + '</div>'
            + '</div>';
    }

    // Otvor fixne pozicovaný dropdown pod tlačidlom; ak dole nie je miesto, otoč ho nahor
    function openFixedDrop($btn, $drop) {
        var rect = $btn[0].getBoundingClientRect();
        $drop.css({ top: '-9999px', left: rect.left + 'px' }).addClass('open');
        var h = $drop.outerHeight();
        var top = rect.bottom + 4;
        if (top + h > window.innerHeight - 8) { top = rect.top - h - 4; }
        if (top < 8) { top = 8; }
        $drop.css('top', top + 'px');
    }

    function getCena(fmt, tyzdne) {         // bez DPH — pre fakturáciu a ukladanie
        tyzdne = parseInt(tyzdne, 10) || 1;
        var v = CENNIK.vylep && CENNIK.vylep[fmt];
        if (!v) { return 0; }
        var total = 0;
        while (tyzdne > 0) {
            var t = Math.min(5, tyzdne);
            total += parseFloat(v[t] && v[t].bez || 0);
            tyzdne -= t;
        }
        return total;
    }
    function getSDph(fmt, tyzdne) {         // s DPH — pre zobrazenie v tabuľke
        tyzdne = parseInt(tyzdne, 10) || 1;
        var v = CENNIK.vylep && CENNIK.vylep[fmt];
        if (!v) { return 0; }
        var total = 0;
        while (tyzdne > 0) {
            var t = Math.min(5, tyzdne);
            total += parseFloat(v[t] && v[t].s || 0);
            tyzdne -= t;
        }
        return total;
    }

    /* ===== DÁTUM — iba utorky cez Flatpickr ===== */
    function isoToDM(iso) {
        if (!iso) return '';
        var p = iso.split('-');
        if (p.length < 3) return iso;
        return p[2] + '.' + p[1];
    }

    function setDateDisplay($txt, iso) {
        var dm = isoToDM(iso);
        if (dm) { $txt.text(dm).removeClass('kp-date-empty'); }
        else     { $txt.html('&#128197;').addClass('kp-date-empty'); }
    }

    function initDateShort($tr) {
        $tr.find('.kp-date-real').each(function () {
            var $inp = $(this);
            var $txt = $inp.siblings('.kp-date-txt');
            var isOd = $inp.hasClass('kp-od');

            // Inicializuj Flatpickr — iba utorky
            var fp = flatpickr($inp[0], {
                dateFormat:  'Y-m-d',
                clickOpens:  false,
                allowInput:  false,
                appendTo:    document.body,
                locale:      { firstDayOfWeek: 1 },
                disable:     isOd ? [ function(date) { return date.getDay() !== 2; } ] : [],
                onChange: function(selectedDates, dateStr) {
                    if (!selectedDates.length) { return; }
                    $inp.val(dateStr);
                    setDateDisplay($txt, dateStr);
                    syncDates($tr, isOd ? 'od' : 'do');
                    recalcRow($tr);
                    recalcTotals();
                }
            });

            $txt.on('click', function () { fp.open(); });
            setDateDisplay($txt, $inp.val()); // počiatočné zobrazenie
        });
    }
    function recalcRow($tr) {
        var fmt    = $tr.find('.kp-format').val();
        var tyzdne = parseInt($tr.find('.kp-tyzdne').val(), 10);
        var kusy   = parseInt($tr.find('.kp-kusy').val(), 10) || 0;
        var platba = $tr.find('.kp-platba-hidden').val();
        var tlac   = parseNum($tr.find('.kp-tlac').val());
        var ine    = parseNum($tr.find('.kp-ine').val());

        // Cenník a výlep len ak sú zadané týždne
        if (!tyzdne || tyzdne < 1) {
            $tr.find('.kp-cena').val(0);
            $tr.find('.kp-cena-val').text('—');
            $tr.find('.kp-vylep-val').text('—');
            $tr.find('.kp-hot-val').text('—');
            $tr.find('.kp-fak-val').text('—');
            $tr.find('.kp-zad-val').text('—');
            $tr.find('.kp-kar-val').text('—');
            return;
        }

        var cenaBez  = getCena(fmt, tyzdne);
        var cenaSDph = getSDph(fmt, tyzdne);
        var vylep    = cenaSDph * kusy;
        var spolu    = vylep + tlac + ine;

        $tr.find('.kp-cena').val(cenaBez.toFixed(4));
        $tr.find('.kp-cena-val').text(cenaSDph > 0 ? cenaSDph.toFixed(2).replace('.', ',') + ' €' : '—');
        $tr.find('.kp-vylep-val').text(f2(vylep));
        $tr.find('.kp-hot-val').text(f2(platba === 'Hotovosť' ? spolu : 0));
        $tr.find('.kp-fak-val').text(f2(platba === 'Faktúra'  ? spolu : 0));
        $tr.find('.kp-zad-val').text(f2(platba === 'Zadarmo'  ? spolu : 0));
        $tr.find('.kp-kar-val').text(f2(platba === 'Karta'    ? spolu : 0));
    }

    function setDoDate($tr, isoDate) {
        var $do = $tr.find('.kp-do');
        // Nastav hodnotu priamo na input — obíď Flatpickr disable filter
        $do.val(isoDate);
        // Ak má Flatpickr, aktualizuj jeho internú hodnotu bez triggerovania onChange
        if ($do[0]._flatpickr) {
            $do[0]._flatpickr.setDate(isoDate, false, 'Y-m-d');
        }
        setDateDisplay($tr.find('.kp-do-txt'), isoDate);
    }

    function syncDates($tr, changed) {
        var $od = $tr.find('.kp-od'), $do = $tr.find('.kp-do'), $t = $tr.find('.kp-tyzdne');
        var od = $od.val(), doo = $do.val(), t = parseInt($t.val(), 10) || 1;

        if ((changed === 'od' || changed === 'tyzdne') && od) {
            var newDo = addDays(od, t * 7);
            setDoDate($tr, newDo);
        } else if (changed === 'do' && od && doo) {
            var diff = Math.round((new Date(doo) - new Date(od)) / 604800000);
            if (diff > 0) { $t.val(diff); }
        }

        setDateDisplay($tr.find('.kp-od-txt'), $od.val());
        setDateDisplay($tr.find('.kp-do-txt'), $do.val());
    }

    /* ===== AUTOCOMPLETE ORGANIZÁCIA ===== */
    function acFilter(query) {
        var q = query.toLowerCase().trim();
        if (!q) { return ORGS; }
        return ORGS.filter(function (o) {
            return (o.odberatel || '').toLowerCase().indexOf(q) >= 0
                || o.nazov.toLowerCase().indexOf(q) >= 0;
        });
    }

    function acShowDrop($wrap, matches, query) {
        var $drop = $wrap.find('.kp-ac-drop');
        $drop.empty();
        matches.forEach(function (o) {
            var label = o.odberatel || o.nazov;
            $('<div class="kp-ac-item"></div>').text(label).data('id', o.id).data('nazov', o.odberatel || o.nazov).appendTo($drop);
        });
        if (query.trim() && !matches.some(function (o) { return (o.odberatel || o.nazov).toLowerCase() === query.toLowerCase(); })) {
            $('<div class="kp-ac-item kp-ac-new"></div>').html('➕ Pridať: <strong>' + $('<span>').text(query).html() + '</strong>').data('new', query).appendTo($drop);
        }
        $drop.show();
        // Ak pod bunkou nie je miesto (posledné riadky), otoč zoznam nahor
        $drop.removeClass('kp-ac-up');
        var rect = $wrap[0].getBoundingClientRect();
        var h = $drop.outerHeight();
        if (rect.bottom + h + 6 > window.innerHeight - 44) { $drop.addClass('kp-ac-up'); }
    }

    function acHide($wrap) { $wrap.find('.kp-ac-drop').hide(); }

    function acSelect($wrap, id, nazov) {
        $wrap.find('.kp-ac-input').val(nazov);
        $wrap.find('.kp-ac-id').val(id);
        $wrap.find('.kp-ac-nazov').val(nazov);
        $wrap.closest('tr').attr('data-org', nazov);
        acHide($wrap);
    }

    function initAC($wrap) {
        var $inp = $wrap.find('.kp-ac-input');
        var timer;

        $inp.on('input', function () {
            clearTimeout(timer);
            var q = $(this).val().trim();
            timer = setTimeout(function () {
                var matches = acFilter(q);
                acShowDrop($wrap, matches, q);
            }, 100);
        });

        $inp.on('focus', function () {
            // Zobraz celý zoznam — prázdny input = všetci odberatelia
            var q = $(this).val().trim();
            var matches = q ? acFilter(q) : ORGS;
            acShowDrop($wrap, matches, q);
        });

        $inp.on('keydown', function (e) {
            if (e.key === 'Escape') { acHide($wrap); }
            if (e.key === 'ArrowDown') {
                $wrap.find('.kp-ac-item:first').focus(); e.preventDefault();
            }
        });

        $wrap.on('click', '.kp-ac-item', function () {
            var $item = $(this);
            if ($item.data('new')) {
                // Pridať novú organizáciu
                var nazov = $item.data('new');
                $.post(KP.ajax, { action: 'kass_pub_save_org', nonce: KP.nonce, nazov: nazov }, function (res) {
                    if (res.success) {
                        var org = res.data;
                        ORGS.push({ id: org.id, nazov: org.nazov });
                        // doplň do filtrovacieho selectu
                        $('#kp-filter-org').append($('<option></option>').val(org.nazov).text(org.nazov));
                        acSelect($wrap, org.id, org.nazov);
                        status('Organizácia pridaná ✓', true);
                    }
                });
            } else {
                acSelect($wrap, $item.data('id'), $item.data('nazov'));
            }
        });

        $wrap.find('.kp-ac-drop').on('keydown', '.kp-ac-item', function (e) {
            if (e.key === 'Enter') { $(this).click(); }
            if (e.key === 'ArrowDown') { $(this).next('.kp-ac-item').focus(); e.preventDefault(); }
            if (e.key === 'ArrowUp')   { $(this).prev('.kp-ac-item').length ? $(this).prev('.kp-ac-item').focus() : $inp.focus(); e.preventDefault(); }
            if (e.key === 'Escape')    { acHide($wrap); $inp.focus(); }
        });
    }

    // Zatvoriť dropdown kliknutím mimo
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.kp-ac-wrap').length) { $('.kp-ac-drop').hide(); }
    });

    /* ===== ULOŽIŤ ===== */
    function saveRow($tr) {
        var id       = parseInt($tr.data('id'), 10) || 0;
        var $wrap    = $tr.find('.kp-ac-wrap');
        var orgId    = $wrap.find('.kp-ac-id').val();
        var orgNazov = $wrap.find('.kp-ac-nazov').val() || $wrap.find('.kp-ac-input').val();

        var data = {
            action: 'kass_pub_save_vylep', nonce: KP.nonce, id: id,
            platba:            $tr.find('.kp-platba-hidden').val() || 'Zadarmo',
            format:            $tr.find('.kp-format').val(),
            organizacia_id:    orgId,
            organizacia_nazov: orgNazov,
            nazov_akcie:       $tr.find('.kp-akcia').val(),
            datum_od:          $tr.find('.kp-od').val(),
            datum_do:          $tr.find('.kp-do').val(),
            tyzdne:            $tr.find('.kp-tyzdne').val(),
            kusy:              $tr.find('.kp-kusy').val(),
            cennik_cena:       $tr.find('.kp-cena').val(),
            tlac:              String($tr.find('.kp-tlac').val()).replace(/\s*€/g, '').replace(',', '.'),
            ine:               String($tr.find('.kp-ine').val()).replace(/\s*€/g, '').replace(',', '.'),
        };

        $tr.addClass('kp-saving');
        $.post(KP.ajax, data, function (res) {
            $tr.removeClass('kp-saving');
            if (res.success) {
                $tr.data('id', res.data.id).attr('data-id', res.data.id)
                   .attr('data-org', orgNazov)
                   .removeClass('kp-row-new');
                // Aktualizuj odkaz na faktúru
                $tr.find('.kp-btn-fakt').attr('href', KP.faktUrl + res.data.id);
                status('Uložené ✓', true);
                recalcTotals();
            } else {
                status('Chyba pri ukladaní.', false);
            }
        });
    }

    /* ===== ZMAZAŤ ===== */
    function deleteRow($tr) {
        var id = parseInt($tr.data('id'), 10);
        if (!id) { $tr.remove(); renumber(); recalcTotals(); return; }
        if (!confirm('Naozaj zmazať tento riadok?')) { return; }
        $.post(KP.ajax, { action: 'kass_pub_delete_vylep', nonce: KP.nonce, id: id }, function (res) {
            if (res.success) {
                $tr.fadeOut(150, function () { $(this).remove(); renumber(); recalcTotals(); });
                status('Zmazané.', true);
            }
        });
    }

    /* ===== ODDEĽOVAČ TÝŽDŇOV ===== */
    function updateWeekSeparators() {
        var $rows = $('#kp-tbody .kp-row:visible');
        var prevOd = null;
        $rows.each(function () {
            var $tr = $(this);
            var od = $tr.find('.kp-od').val();
            if (prevOd !== null && od && od !== prevOd) {
                $tr.addClass('kp-week-sep');
            } else {
                $tr.removeClass('kp-week-sep');
            }
            if (od) { prevOd = od; }
        });
    }
    function applyFilter() {
        var orgVal    = $('#kp-filter-org').val();
        var mesVal    = $('#kp-filter-mes').val();
        var platbaVal = $('#kp-filter-platba').val();
        var $rows     = $('#kp-tbody .kp-row');
        var visible   = 0;

        $rows.each(function () {
            var $tr       = $(this);
            var orgMatch    = !orgVal    || $tr.attr('data-org') === orgVal;
            var mesMatch    = !mesVal    || String($tr.attr('data-mes')) === String(mesVal);
            var platbaMatch = !platbaVal || $tr.find('.kp-platba-hidden').val() === platbaVal;
            var show = orgMatch && mesMatch && platbaMatch;
            $tr.toggle(show);
            if (show) { visible++; }
        });

        var hasFilter = orgVal || mesVal || platbaVal;
        $('#kp-filter-clear').toggle(!!hasFilter);
        $('#kp-count').text(visible + ' riadkov');
        recalcTotals();
    }

    /* ===== PREČÍSLOVANIE ===== */
    function renumber() {
        var n = 1;
        // Renumber visible rows
        function renumberRows() {
            var n = 1;
            $('#kp-tbody .kp-row:visible').each(function () {
                $(this).find('.kp-num-edit').text(n++);
            });
        }

        // Sort rows by number on blur of num-edit
        $tbody.on('blur', '.kp-num-edit', function () {
            var $span = $(this);
            var newNum = parseInt($span.text(), 10);
            if (isNaN(newNum) || newNum < 1) { renumberRows(); return; }

            var $row = $span.closest('.kp-row');
            var $rows = $('#kp-tbody .kp-row').detach();

            // Set sort key on each row
            $rows.each(function () {
                var n = parseInt($(this).find('.kp-num-edit').text(), 10) || 999;
                $(this).data('sortnum', n);
            });

            // Sort by sortnum
            $rows.sort(function (a, b) {
                return $(a).data('sortnum') - $(b).data('sortnum');
            });

            $tbody.append($rows);
            renumberRows();
            recalcTotals();
        });
    }

    /* ===== SÚČTY (len viditeľné riadky) ===== */
    function recalcTotals() {
        updateWeekSeparators();
        var sv = 0, st = 0, si = 0, sh = 0, sf = 0, sz = 0, sk = 0;
        $('#kp-tbody .kp-row:visible').each(function () {
            var $tr    = $(this);
            var fmt    = $tr.find('.kp-format').val();
            var tyzdne = parseInt($tr.find('.kp-tyzdne').val(), 10) || 1;
            var kusy   = parseInt($tr.find('.kp-kusy').val(), 10) || 0;
            var tlac   = parseNum($tr.find('.kp-tlac').val());
            var ine    = parseNum($tr.find('.kp-ine').val());
            var vylep  = getSDph(fmt, tyzdne) * kusy;   // s DPH
            var spolu  = vylep + tlac + ine;
            var platba = $tr.find('.kp-platba-hidden').val();
            sv += vylep; st += tlac; si += ine;
            if      (platba === 'Hotovosť') { sh += spolu; }
            else if (platba === 'Faktúra')  { sf += spolu; }
            else if (platba === 'Karta')    { sk += spolu; }
            else                            { sz += spolu; }
        });
        function fmt(n) { return n > 0 ? n.toFixed(2).replace('.', ',') + ' €' : '—'; }
        $('#kp-sum-vylep').text(fmt(sv));
        $('#kp-sum-tlac').text(fmt(st));
        $('#kp-sum-ine').text(fmt(si));
        $('#kp-sum-hot').text(fmt(sh));
        $('#kp-sum-fak').text(fmt(sf));
        $('#kp-sum-zad').text(fmt(sz));
        $('#kp-sum-kar').text(fmt(sk));
    }

    /* ===== NOVÝ RIADOK ===== */
    function newRowHtml(num) {
        var pOpts = ['Zadarmo', 'Faktúra', 'Hotovosť', 'Karta'].map(function (p) { return '<option>' + p + '</option>'; }).join('');
        var fOpts = ['A4', 'A3', 'A2', 'A1'].map(function (f) { return '<option' + (f === 'A3' ? ' selected' : '') + '>' + f + '</option>'; }).join('');
        return '<tr data-id="0" data-org="" class="kp-row kp-row-new">'
            + '<td class="kp-num"><span class="kp-num-edit" contenteditable="true">' + num + '</span>.</td>'
            + '<td>' + platbaDropHtml('Zadarmo') + '</td>'
            + '<td>' + fmtDropHtml('A3') + '</td>'
            + '<td><div class="kp-ac-wrap">'
            +   '<input type="text" class="kp-inp kp-ac-input" placeholder="Začni písať…" autocomplete="off">'
            +   '<input type="hidden" class="kp-ac-id" value="0">'
            +   '<input type="hidden" class="kp-ac-nazov" value="">'
            +   '<div class="kp-ac-drop" style="display:none;"></div>'
            + '</div></td>'
            + '<td><input type="text" class="kp-inp kp-akcia" placeholder="Názov akcie"></td>'
            + '<td class="kp-date-cell"><span class="kp-date-txt kp-od-txt"></span><input type="date" class="kp-date-real kp-od" name="datum_od" value=""></td>'
            + '<td class="kp-date-cell"><span class="kp-date-txt kp-do-txt"></span><input type="date" class="kp-date-real kp-do" name="datum_do" value=""></td>'
            + '<td><input type="number" class="kp-inp kp-tyzdne" name="tyzdne" placeholder="T" min="1" max="20"></td>'
            + '<td><input type="number" class="kp-inp kp-kusy" name="kusy" placeholder="ks" min="0"></td>'
            + '<td class="kp-calc"><span class="kp-cena-val">—</span><input type="hidden" class="kp-cena" value="0"></td>'
            + '<td class="kp-calc kp-bold"><span class="kp-vylep-val">—</span></td>'
            + '<td><input type="text" class="kp-inp kp-tlac" placeholder="0"></td>'
            + '<td><input type="text" class="kp-inp kp-ine" placeholder="0"></td>'
            + '<td class="kp-calc"><span class="kp-hot-val">—</span></td>'
            + '<td class="kp-calc"><span class="kp-fak-val">—</span></td>'
            + '<td class="kp-calc"><span class="kp-zad-val">—</span></td>'
            + '<td class="kp-calc"><span class="kp-kar-val">—</span></td>'
            + '<td class="kp-actions">'
            +   '<button type="button" class="kp-btn-save" title="Uložiť">💾</button>'
            +   '<a href="#" class="kp-btn-fakt" title="Podklad k faktúre">📄</a>'
            +   '<button type="button" class="kp-btn-del" title="Zmazať">✕</button>'
            + '</td></tr>';
    }

    /* ===== INIT ===== */
    $(function () {
        var $tbody = $('#kp-tbody');

        // Init existujúcich riadkov
        $tbody.find('.kp-row').each(function () {
            initAC($(this).find('.kp-ac-wrap'));
            initDateShort($(this));
            recalcRow($(this));
        });

        recalcTotals();
        $('#kp-count').text($tbody.find('.kp-row').length + ' riadkov');

        // Pridať riadok
        $('#kp-add-row').on('click', function () {
            var num = $tbody.find('.kp-row').length + 1;
            var $tr = $(newRowHtml(num));
            $tbody.append($tr);
            initAC($tr.find('.kp-ac-wrap'));
            initDateShort($tr);
            recalcRow($tr);
            $tr.find('.kp-akcia').focus();
            $tr[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        // Zmeny v riadku
        $tbody.on('change', '.kp-format, .kp-kusy, .kp-tlac, .kp-ine', function () {
            recalcRow($(this).closest('tr')); recalcTotals();
        });

        // Formátovanie Tlač a Iné — blur: pridaj €, focus: zobraz číslo
        $tbody.on('blur', '.kp-tlac, .kp-ine', function () {
            var val = parseNum($(this).val());
            $(this).val(val > 0 ? val.toFixed(2).replace('.', ',') + ' €' : '');
        });
        $tbody.on('focus', '.kp-tlac, .kp-ine', function () {
            var val = parseNum($(this).val());
            $(this).val(val > 0 ? val.toFixed(2).replace('.', ',') : '');
        });
        $tbody.on('change', '.kp-tyzdne', function () { syncDates($(this).closest('tr'), 'tyzdne'); recalcRow($(this).closest('tr')); recalcTotals(); });

        // Uložiť
        $tbody.on('click', '.kp-btn-save', function () { saveRow($(this).closest('tr')); });
        // Zmazať
        $tbody.on('click', '.kp-btn-del',  function () { deleteRow($(this).closest('tr')); });
        // Enter = uložiť
        $tbody.on('keydown', 'input.kp-inp:not(.kp-ac-input)', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); saveRow($(this).closest('tr')); }
        });

        // Format dropdown
        $tbody.on('click', '.kp-fmt-btn', function (e) {
            e.stopPropagation();
            var $btn  = $(this);
            var $drop = $btn.siblings('.kp-fmt-drop');
            var wasOpen = $drop.hasClass('open');
            $('.kp-fmt-drop, .kp-platba-drop').removeClass('open');
            if (!wasOpen) { openFixedDrop($btn, $drop); }
        });
        $tbody.on('click', '.kp-fmt-opt', function (e) {
            e.stopPropagation();
            var val = $(this).data('val');
            var $wrap = $(this).closest('.kp-fmt-wrap');
            $wrap.find('.kp-fmt-btn').text(val);
            $wrap.find('.kp-format').val(val);
            $wrap.find('.kp-fmt-drop').removeClass('open');
            recalcRow($(this).closest('tr'));
            recalcTotals();
        });
        $(document).on('click', function () { $('.kp-fmt-drop').removeClass('open'); });

        // Platba dropdown — otvoriť/zatvoriť
        $tbody.on('click', '.kp-platba-btn', function (e) {
            e.stopPropagation();
            var $btn  = $(this);
            var $drop = $btn.siblings('.kp-platba-drop');
            var wasOpen = $drop.hasClass('open');
            $('.kp-platba-drop').removeClass('open');
            if (!wasOpen) { openFixedDrop($btn, $drop); }
        });
        // Výber možnosti
        $tbody.on('click', '.kp-platba-opt', function (e) {
            e.stopPropagation();
            var $wrap = $(this).closest('.kp-platba-wrap');
            var val   = $(this).data('val');
            var full  = $(this).data('full');
            $wrap.find('.kp-platba-btn').text(val).attr('data-val', val).attr('data-full', full);
            $wrap.find('.kp-platba-hidden').val(full);
            $wrap.find('.kp-platba-drop').removeClass('open');
            recalcRow($(this).closest('tr'));
            recalcTotals();
        });
        // Zatvoriť kliknutím mimo
        $(document).on('click', function () { $('.kp-platba-drop').removeClass('open'); });

        /* ===== TLAČ MODAL ===== */
        var $tlacModal = $('#kp-tlac-modal');
        var $tlacRow = null;
        var tlacTC = KP.tlacCennik || { A4: {cb:0.15,cb2:0.30,far:0.70}, A3: {cb:0.25,cb2:0.50,far:1.40} };

        function tlacLineHtml() {
            return '<div class="kp-tlac-line">'
                + '<select class="tl-fmt"><option value="A4">A4</option><option value="A3" selected>A3</option></select>'
                + '<select class="tl-typ"><option value="cb">ČB</option><option value="cb2">ČB obojstr.</option><option value="far" selected>Farebne</option></select>'
                + '<input type="number" class="tl-ks" value="1" min="1">'
                + '<span class="kp-tlac-line-lbl tl-suma">0,00 €</span>'
                + '<button type="button" class="kp-tlac-line-del" title="Odstrániť">✕</button>'
                + '</div>';
        }

        function tlacRecalc() {
            var total = 0;
            $('#kp-tlac-lines .kp-tlac-line').each(function () {
                var fmt   = $(this).find('.tl-fmt').val();
                var typ   = $(this).find('.tl-typ').val();
                var ks    = parseInt($(this).find('.tl-ks').val(), 10) || 1;
                var cena  = tlacTC[fmt] && tlacTC[fmt][typ] ? tlacTC[fmt][typ] : 0;
                var sub   = cena * ks;
                $(this).find('.tl-suma').text(sub > 0 ? sub.toFixed(2).replace('.', ',') + ' €' : '0,00 €');
                total += sub;
            });
            $('#kp-tlac-cena').text(total > 0 ? total.toFixed(2).replace('.', ',') + ' €' : '—');
            return total;
        }

        function tlacOpen($tr) {
            $tlacRow = $tr;
            $('#kp-tlac-lines').empty();

            // Obnov uložené riadky ak existujú
            var saved = $tr.data('tlac-lines');
            if (saved && saved.length) {
                saved.forEach(function (ln) {
                    var $line = $(tlacLineHtml());
                    $line.find('.tl-fmt').val(ln.fmt);
                    $line.find('.tl-typ').val(ln.typ);
                    $line.find('.tl-ks').val(ln.ks);
                    $('#kp-tlac-lines').append($line);
                });
            } else {
                $('#kp-tlac-lines').append(tlacLineHtml());
            }
            tlacRecalc();
            $tlacModal.addClass('open');
        }

        $('#kp-tlac-lines').on('change input', 'select, input', tlacRecalc);
        $('#kp-tlac-lines').on('click', '.kp-tlac-line-del', function () {
            $(this).closest('.kp-tlac-line').remove();
            if ($('#kp-tlac-lines .kp-tlac-line').length === 0) {
                $('#kp-tlac-lines').append(tlacLineHtml());
            }
            tlacRecalc();
        });

        $('#kp-tlac-add-line').on('click', function () {
            $('#kp-tlac-lines').append(tlacLineHtml());
            tlacRecalc();
        });

        $('#kp-tlac-cancel').on('click', function () { $tlacModal.removeClass('open'); });
        $tlacModal.on('click', function (e) { if (e.target === this) { $tlacModal.removeClass('open'); } });

        $('#kp-tlac-clear').on('click', function () {
            if ($tlacRow) {
                $tlacRow.data('tlac-lines', null);
                $tlacRow.find('.kp-tlac').val('');
                recalcRow($tlacRow);
                recalcTotals();
            }
            $tlacModal.removeClass('open');
        });

        $('#kp-tlac-ok').on('click', function () {
            if (!$tlacRow) { return; }
            var total = tlacRecalc();
            var lines = [];
            $('#kp-tlac-lines .kp-tlac-line').each(function () {
                lines.push({ fmt: $(this).find('.tl-fmt').val(), typ: $(this).find('.tl-typ').val(), ks: $(this).find('.tl-ks').val() });
            });
            $tlacRow.data('tlac-lines', lines);
            var formatted = total > 0 ? total.toFixed(2).replace('.', ',') + ' €' : '';
            $tlacRow.find('.kp-tlac').val(formatted);
            recalcRow($tlacRow);
            recalcTotals();
            $tlacModal.removeClass('open');
        });

        $tbody.on('click', '.kp-tlac', function () {
            tlacOpen($(this).closest('.kp-row'));
        });
        /* ===== VÝLEP ZOZNAM ===== */
        window.kassVylepList = function () {
          try {
            console.log('kassVylepList called');
            var $modal = $('#kp-vylep-list-modal');
            console.log('modal found:', $modal.length, $modal[0]);
            var today   = new Date();
            var dow     = today.getDay();
            var diffToTue = (dow >= 2) ? (dow - 2) : (dow + 5);
            var thisTue = new Date(today); thisTue.setDate(today.getDate() - diffToTue);
            var thisTueStr = thisTue.toISOString().slice(0, 10);

            var neprelepit = [], prelepit = [];

            $('#kp-tbody .kp-row:visible').each(function () {
                var $tr   = $(this);
                var doVal  = $tr.find('.kp-do').val();
                var platba = $tr.find('.kp-platba-hidden').val();
                var fmt    = $tr.find('.kp-format').val();
                var org    = $tr.attr('data-org') || $tr.find('.kp-ac-input').val() || '';
                var akcia  = $tr.find('.kp-akcia').val() || '';
                var od     = $tr.find('.kp-od').val();
                if (!doVal) { return; }
                var isPaid = (platba === 'H' || platba === 'FA' || platba === 'K');
                var item   = { fmt: fmt || '', org: org, akcia: akcia, od: od, do: doVal };
                if (doVal === thisTueStr && isPaid) { prelepit.push(item); }
                else if (doVal > thisTueStr)        { neprelepit.push(item); }
            });

            function fd(iso) { if (!iso) { return ''; } var p = iso.split('-'); return p[2] + '.' + p[1] + '.'; }
            function tbl(items) {
                if (!items.length) { return '<tr><td colspan="3" style="padding:8px 10px;color:#aaa;font-style:italic;font-size:9pt;">žiadne položky</td></tr>'; }
                // Zoskupiť podľa organizácie
                var groups = {};
                var order  = [];
                items.forEach(function (r) {
                    var key = r.org || '—';
                    if (!groups[key]) { groups[key] = []; order.push(key); }
                    groups[key].push(r);
                });
                var rows = '';
                order.forEach(function (org) {
                    var orgItems = groups[org];
                    orgItems.forEach(function (r, i) {
                        rows += '<tr style="border-bottom:1px solid #e8e8e8;">'
                            + (i === 0
                                ? '<td rowspan="' + orgItems.length + '" style="padding:6px 10px;font-weight:800;vertical-align:top;border-right:2px solid #ddd;min-width:110px;">' + org + '</td>'
                                : '')
                            + '<td style="padding:6px 10px;">' + (r.akcia || '—') + '</td>'
                            + '<td style="padding:6px 10px;font-weight:700;text-align:center;white-space:nowrap;width:40px;">' + r.fmt + '</td>'
                            + '</tr>';
                    });
                    // Oddeľovač medzi organizáciami
                    rows += '<tr><td colspan="3" style="height:4px;background:#f5f5f5;"></td></tr>';
                });
                return rows;
            }
            var ds = today.toLocaleDateString('sk', { day:'numeric', month:'long', year:'numeric' });
            var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
                + 'body{font-family:Arial,Helvetica,sans-serif;font-size:10pt;padding:12mm 14mm;color:#111;margin:0;}'
                + 'h1{font-size:15pt;margin:0 0 3px;font-weight:900;letter-spacing:-.3px;}'
                + '.sub{font-size:9pt;color:#666;margin-bottom:14px;}'
                + 'h2{font-size:10.5pt;margin:18px 0 6px;padding:5px 10px;border-radius:5px;font-weight:800;}'
                + '.nep{background:#fdecea;color:#b71c1c;}'
                + '.pre{background:#e8f5e9;color:#1b5e20;}'
                + 'table{width:100%;border-collapse:collapse;margin-bottom:4px;}'
                + 'th{text-align:left;padding:5px 10px;font-size:9pt;background:#f0f0f0;border-bottom:2px solid #ccc;}'
                + '@page{size:A4 portrait;margin:10mm;}'
                + '</style></head><body>'
                + '<h1>Výlep plagátov</h1><div class="sub">KaSS Prievidza &nbsp;·&nbsp; ' + ds + ' &nbsp;·&nbsp; týždeň od ' + fd(thisTueStr) + '</div>'
                + '<h2 class="nep">🔴 NEPRELEPIŤ &nbsp;<span style="font-size:9pt;font-weight:400;">(' + neprelepit.length + ' položiek)</span></h2>'
                + '<table><thead><tr><th>Organizácia</th><th>Názov akcie</th><th>Fmt</th></tr></thead><tbody>' + tbl(neprelepit) + '</tbody></table>'
                + '<h2 class="pre">🟢 PRELEPIŤ &nbsp;<span style="font-size:9pt;font-weight:400;">(' + prelepit.length + ' položiek)</span></h2>'
                + '<table><thead><tr><th>Organizácia</th><th>Názov akcie</th><th>Fmt</th></tr></thead><tbody>' + tbl(prelepit) + '</tbody></table>'
                + '</body></html>';

            var ifr = document.getElementById('kp-vylep-list-iframe');
            ifr.src = 'about:blank';
            setTimeout(function () { ifr.contentDocument.open(); ifr.contentDocument.write(html); ifr.contentDocument.close(); }, 60);
            $('#kp-vylep-list-modal').addClass('open');
            $('body').css('overflow', 'hidden');
          } catch(e) { console.error('Výlep list error:', e); alert('Chyba: ' + e.message); }
        };
        $(document).on('click', '#kp-vylep-list-close, .kp-vylep-list-close', function () { $('#kp-vylep-list-modal').removeClass('open'); $('body').css('overflow', ''); });
        $(document).on('click', '#kp-vylep-list-print', function () { var ifr = document.getElementById('kp-vylep-list-iframe'); if (ifr && ifr.contentWindow) { ifr.contentWindow.focus(); ifr.contentWindow.print(); } });

        $tbody.on('click', '.kp-btn-fakt', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href || href === '#') { return; }
            // Pridaj embed=1 aby sa zobrazil len obsah bez WP adminu
            var embedUrl = href + (href.indexOf('?') >= 0 ? '&' : '?') + 'embed=1';
            $('#kp-fakt-iframe').attr('src', embedUrl);
            $('#kp-fakt-modal').addClass('open');
            $('body').css('overflow', 'hidden');
        });
        $('#kp-modal-close-btn').on('click', function () {
            $('#kp-fakt-modal').removeClass('open');
            $('#kp-fakt-iframe').attr('src', 'about:blank');
            $('body').css('overflow', '');
        });
        $('#kp-fakt-modal').on('click', function (e) {
            if (e.target === this) { $('#kp-modal-close-btn').trigger('click'); }
        });

        // Filter
        $('#kp-filter-org').on('change', applyFilter);
        $('#kp-filter-mes').on('change', applyFilter);
        $('#kp-filter-platba').on('change', applyFilter);
        $('#kp-filter-clear').on('click', function () {
            $('#kp-filter-org').val('');
            $('#kp-filter-mes').val('');
            $('#kp-filter-platba').val('');
            applyFilter();
        });

        // Aplikácia je position: fixed — presuň ju priamo pod <body>, aby jej
        // pozíciu nemohol ovplyvniť žiadny wrapper témy (transform/filter mení
        // referenčný bod fixed elementov). Zároveň zamkni rolovanie stránky.
        if (document.getElementById('kp-app')) {
            var fw = document.querySelector('.kp-fullwidth');
            if (fw && fw.parentNode !== document.body) {
                document.body.appendChild(fw);
            }
            if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
            document.documentElement.classList.add('kp-scroll-lock');
            document.body.classList.add('kp-scroll-lock');
            window.scrollTo(0, 0);

            // Štart na konci zoznamu rieši CSS (flex-direction: column-reverse
            // na .kp-table-wrap) — scroll 0 je tam spodok, netreba nič strážiť.
        }

        // Ukotvená hlavička stĺpcov — synchronizuj šírky s reálnou tabuľkou
        function syncHeadWidths() {
            var main = document.getElementById('kp-table');
            var head = document.getElementById('kp-head-table');
            if (!main || !head) { return; }
            var src = main.querySelectorAll('thead th');
            var dst = head.querySelectorAll('thead th');
            head.style.width = main.offsetWidth + 'px';
            for (var i = 0; i < src.length && i < dst.length; i++) {
                var w = src[i].offsetWidth + 'px';
                dst[i].style.width = w;
                dst[i].style.minWidth = w;
                dst[i].style.maxWidth = w;
            }
        }
        syncHeadWidths();
        $(window).on('resize', syncHeadWidths);
        if (window.ResizeObserver) {
            var mainTable = document.getElementById('kp-table');
            if (mainTable) { new ResizeObserver(syncHeadWidths).observe(mainTable); }
        }
        // Horizontálny scroll tabuľky posúva aj ukotvenú hlavičku
        var tblWrap  = document.querySelector('.kp-table-wrap');
        var headWrap = document.getElementById('kp-head-wrap');
        if (tblWrap && headWrap) {
            tblWrap.addEventListener('scroll', function () {
                headWrap.scrollLeft = tblWrap.scrollLeft;
            }, { passive: true });
        }

        // Koliesko myši nad okrajmi stránky (mimo boxu tabuľky) scroluje dáta tabuľky
        document.addEventListener('wheel', function (e) {
            var wrap = document.querySelector('.kp-table-wrap');
            if (!wrap) { return; }
            if (wrap.contains(e.target)) { return; } // vnútri tabuľky funguje natívne
            if (e.target.closest && e.target.closest('.kp-modal-overlay, .kp-tlac-modal, .kp-ac-drop, .kp-platba-drop, .kp-fmt-drop, .flatpickr-calendar')) { return; }
            wrap.scrollTop += e.deltaY;
        }, { passive: true });
    });

})(jQuery);
