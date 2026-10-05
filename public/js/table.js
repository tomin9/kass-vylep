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

    // Obsah rozbaľovacích zoznamov sa vkladá až pri prvom otvorení (riadkov je stovky)
    var PLATBA_OPTS_HTML = PLATBA_OPTS.map(function (o) {
        return '<div class="kp-platba-opt" data-val="' + o.val + '" data-full="' + o.full + '">'
             + '<span class="kp-platba-skr">' + o.val + '</span>' + o.full + '</div>';
    }).join('');
    var FMT_OPTS_HTML = ['A4', 'A3', 'A2', 'A1'].map(function (f) {
        return '<div class="kp-fmt-opt" data-val="' + f + '">' + f + '</div>';
    }).join('');

    function platbaDropHtml(selectedFull) {
        var skr = PLATBA_TO_SKR[selectedFull] || 'Z';
        return '<div class="kp-platba-wrap">'
            + '<div class="kp-platba-btn" data-val="' + skr + '" data-full="' + selectedFull + '">' + skr + '</div>'
            + '<input type="hidden" class="kp-platba-hidden" name="platba" value="' + selectedFull + '">'
            + '<div class="kp-platba-drop"></div>'
            + '</div>';
    }
    function fmtDropHtml(selected) {
        selected = selected || 'A3';
        return '<div class="kp-fmt-cell"><div class="kp-fmt-wrap">'
            + '<div class="kp-fmt-btn">' + selected + '</div>'
            + '<input type="hidden" class="kp-format" name="format" value="' + selected + '">'
            + '<div class="kp-fmt-drop"></div>'
            + '</div>'
            + '<button type="button" class="kp-orient-btn" data-val="v" title="Na výšku — klik pre zmenu"></button>'
            + '<input type="hidden" class="kp-orient" name="orientacia" value="v">'
            + '</div>';
    }

    var PLOCHY_POPIS = { vsetky: 'Všetky plochy', top10: 'TOP 10', top15: 'TOP 15', top20: 'TOP 20', vyber: 'Vybrané plochy' };
    function plochyCount(sposob, vyberCsv) {
        var all = KP.plochy || [];
        if (sposob === 'vsetky') { return all.length; }
        if (sposob === 'vyber')  { return (vyberCsv || '').split(',').filter(function (x) { return x; }).length; }
        return all.filter(function (p) { return p[sposob]; }).length;
    }
    // Tlačidlo s počtom kusov: zelený okraj = počet kusov sedí s počtom plôch, oranžový = nesedí
    function plochyState($tr) {
        var sposob = $tr.find('.kp-plochy-sposob').val() || 'vsetky';
        var csv    = $tr.find('.kp-plochy-vyber').val() || '';
        var n      = plochyCount(sposob, csv);
        var kusy   = parseInt($tr.find('.kp-kusy').val(), 10) || 0;
        var ok     = n > 0 && n === kusy;
        var txt    = 'Kusy: ' + kusy + ' · ' + (PLOCHY_POPIS[sposob] || sposob) + ': ' + n + ' plôch';
        if (!ok) { txt += ' — nesedia, klikni a prideľ plochy'; }
        $tr.find('.kp-kusy-btn').text(kusy > 0 ? kusy : '—')
           .toggleClass('kp-plochy-ok', ok).toggleClass('kp-plochy-warn', !ok).attr('title', txt);
    }
    function plochyCellHtml(sposob, vyberCsv) {
        return '<div class="kp-plochy-wrap">'
            + '<input type="hidden" class="kp-plochy-sposob" name="plochy_sposob" value="' + (sposob || 'vsetky') + '">'
            + '<input type="hidden" class="kp-plochy-vyber" name="plochy_vyber" value="' + (vyberCsv || '') + '">'
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

    // Riadok je zobrazený, ak ho filter neschoval (rýchlejšie ako :visible, ktoré vynucuje prepočet rozloženia)
    function isRowShown() { return this.style.display !== 'none'; }

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

    // Flatpickr sa vytvára až pri prvom kliknutí na dátum (nie pre každý z ~500 dátumov pri načítaní —
    // každá inštancia totiž vytvorí celý kalendár v DOM).
    function initDateShort($tr) {
        $tr.find('.kp-date-real').each(function () {
            var $inp = $(this);
            var $txt = $inp.siblings('.kp-date-txt');
            var isOd = $inp.hasClass('kp-od');

            $txt.on('click', function () {
                var fp = $inp[0]._flatpickr;
                if (!fp) {
                    fp = flatpickr($inp[0], {
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
                            scheduleSave($tr);
                        }
                    });
                }
                fp.open();
            });
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
        scheduleSave($wrap.closest('tr'));
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

    /* ===== AUTOMATICKÉ UKLADANIE ===== */
    var AUTOSAVE_DELAY = 1200; // ms po poslednej zmene

    function rowHasContent($tr) {
        return !!(($tr.find('.kp-ac-nazov').val() || '').trim()
               || ($tr.find('.kp-ac-input').val() || '').trim()
               || ($tr.find('.kp-akcia').val() || '').trim());
    }

    function scheduleSave($tr) {
        if (!$tr || !$tr.length) { return; }
        var id = parseInt($tr.data('id'), 10) || 0;
        // Nový riadok ulož až keď má aspoň organizáciu alebo názov akcie
        if (!id && !rowHasContent($tr)) { return; }
        clearTimeout($tr.data('kp-save-t'));
        $tr.data('kp-save-t', setTimeout(function () {
            // Ak ešte beží predchádzajúce ukladanie, počkaj naň
            if ($tr.hasClass('kp-saving') || $tr.hasClass('kp-autosave')) {
                scheduleSave($tr);
                return;
            }
            saveRow($tr, true);
        }, AUTOSAVE_DELAY));
    }

    /* ===== ULOŽIŤ ===== */
    function saveRow($tr, auto) {
        var id       = parseInt($tr.data('id'), 10) || 0;
        var $wrap    = $tr.find('.kp-ac-wrap');
        var orgId    = $wrap.find('.kp-ac-id').val();
        var orgNazov = $wrap.find('.kp-ac-nazov').val() || $wrap.find('.kp-ac-input').val();

        var data = {
            action: 'kass_pub_save_vylep', nonce: KP.nonce, id: id,
            platba:            $tr.find('.kp-platba-hidden').val() || 'Zadarmo',
            format:            $tr.find('.kp-format').val(),
            orientacia:        $tr.find('.kp-orient').val() || 'v',
            plochy_sposob:     $tr.find('.kp-plochy-sposob').val() || 'vsetky',
            plochy_vyber:      $tr.find('.kp-plochy-vyber').val() || '',
            organizacia_id:    orgId,
            organizacia_nazov: orgNazov,
            nazov_akcie:       $tr.find('.kp-akcia').val(),
            datum_od:          $tr.find('.kp-od').val(),
            datum_do:          $tr.find('.kp-do').val(),
            tyzdne:            $tr.find('.kp-tyzdne').val(),
            kusy:              $tr.find('.kp-kusy').val(),
            cennik_cena:       $tr.find('.kp-cena').val(),
            tlac:              String($tr.find('.kp-tlac').val()).replace(/\s*€/g, '').replace(',', '.'),
            tlac_bez_dph:      $tr.find('.kp-tlac-bez').val() || 0,
            ine:               String($tr.find('.kp-ine').val()).replace(/\s*€/g, '').replace(',', '.'),
        };

        // Autosave nesmie zablokovať riadok (pointer-events) počas písania
        $tr.addClass(auto ? 'kp-autosave' : 'kp-saving');
        $.post(KP.ajax, data, function (res) {
            $tr.removeClass('kp-saving kp-autosave');
            if (res.success) {
                $tr.data('id', res.data.id).attr('data-id', res.data.id)
                   .attr('data-org', orgNazov)
                   .removeClass('kp-row-new');
                // Aktualizuj odkaz na faktúru
                $tr.find('.kp-btn-fakt').attr('href', KP.faktUrl + res.data.id);
                status('Uložené ✓', true);
                recalcTotals();
                refreshObsadenostIfOpen();
            } else {
                status('Chyba pri ukladaní.', false);
            }
        }).fail(function () {
            $tr.removeClass('kp-saving kp-autosave');
            status('Chyba pri ukladaní.', false);
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
                refreshObsadenostIfOpen();
            }
        });
    }

    /* ===== ODDEĽOVAČ TÝŽDŇOV ===== */
    function updateWeekSeparators() {
        var $rows = $('#kp-tbody .kp-row').filter(isRowShown);
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
            var platbaMatch = !platbaVal || (PLATBA_TO_SKR[$tr.find('.kp-platba-hidden').val()] || '') === platbaVal;
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
            $('#kp-tbody .kp-row').filter(isRowShown).each(function () {
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
        $('#kp-tbody .kp-row').filter(isRowShown).each(function () {
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
            + '<td><div class="kp-kusy-wrap"><button type="button" class="kp-kusy-btn" title="Kusy a plochy">—</button><input type="hidden" class="kp-kusy" name="kusy" value="">' + plochyCellHtml('vsetky', '') + '</div></td>'
            + '<td class="kp-calc"><span class="kp-cena-val">—</span><input type="hidden" class="kp-cena" value="0"></td>'
            + '<td class="kp-calc kp-bold"><span class="kp-vylep-val">—</span></td>'
            + '<td><input type="text" class="kp-inp kp-tlac" placeholder="0"><input type="hidden" class="kp-tlac-bez" value="0"></td>'
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

    /* ===== PLOCHY (vizualizácia obsadenosti) ===== */
    var plochyTabOpen    = false;
    var plochyObsadenost = null; // [plocha_id => {A4,A3,A2,A1}]
    var plochySelectedId = null;

    function fetchObsadenost(cb) {
        $.post(KP.ajax, { action: 'kass_pub_get_obsadenost', nonce: KP.nonce }, function (res) {
            if (res.success) { plochyObsadenost = res.data; }
            if (cb) { cb(); }
        });
    }

    function refreshObsadenostIfOpen() {
        if (!plochyTabOpen) { return; }
        fetchObsadenost(function () { renderPlochyDetail(plochySelectedId); });
    }

    function renderPlochySelector() {
        var $sel = $('#kp-plochy-selector').empty();
        if (plochySelectedId === null && KP.plochy && KP.plochy.length) { plochySelectedId = KP.plochy[0].id; }
        (KP.plochy || []).forEach(function (p) {
            var tagy = [];
            if (p.top10) { tagy.push('t10'); }
            if (p.top15) { tagy.push('t15'); }
            if (p.top20) { tagy.push('t20'); }
            var $btn = $('<button type="button" class="kp-plochy-sel-btn"></button>')
                .text(p.cislo)
                .attr('title', p.nazov || ('Plocha ' + p.cislo))
                .attr('data-id', p.id)
                .addClass(tagy.map(function (t) { return 'kp-plochy-tag-' + t; }).join(' '));
            $sel.append($btn);
        });
        updateSelectorActive();
    }

    function updateSelectorActive() {
        $('#kp-plochy-selector .kp-plochy-sel-btn').removeClass('active').each(function () {
            if (parseInt($(this).attr('data-id'), 10) === plochySelectedId) { $(this).addClass('active'); }
        });
    }

    /* --- Geometria plochy (jednotky = pomer strán papiera) ---
       Horný rad: modul 100 × 141,4 (A1 na výšku). Spodný rad: modul 70,7 × 100 (A2 na výšku). */
    var PL = { UW: 100, UH: 141.42, VW: 70.71, VH: 100 };
    var PLOCHY_NAZVY = {
        A1v: 'A1 výška', A2s: 'A2 šírka', A3v: 'A3 výška',
        A2v: 'A2 výška', A3s: 'A3 šírka', A1s: 'A1 šírka', A4: 'A4'
    };

    function plochyLayoutTop(n, c) {
        var full = Math.floor(n), half = (n - full) >= 0.5 ? 1 : 0;
        var cells = [], over = { A1v: 0, A2s: 0, A3v: 0 };
        var a1 = Math.min(c.A1v || 0, full);
        over.A1v = (c.A1v || 0) - a1;
        for (var i = 0; i < a1; i++) { cells.push({ k: 'A1v', x: i * PL.UW, y: 0, w: PL.UW, h: PL.UH }); }
        var restCols = full - a1;
        var shelfH = PL.UH / 2;
        var a2 = Math.min(c.A2s || 0, restCols * 2);
        over.A2s = (c.A2s || 0) - a2;
        var a3 = c.A3v || 0;
        // A3 na výšku: najprv polovičný modul (1 na poličku), potom voľné poličky v plných moduloch (2 na poličku)
        var a3half = Math.min(a3, half * 2); a3 -= a3half;
        var freeShelves = restCols * 2 - a2;
        var a3full = Math.min(a3, freeShelves * 2); a3 -= a3full;
        over.A3v = a3;
        var x0 = a1 * PL.UW;
        for (var s = 0; s < restCols * 2; s++) {
            var x = x0 + Math.floor(s / 2) * PL.UW, y = (s % 2) * shelfH;
            if (s < a2) { cells.push({ k: 'A2s', x: x, y: y, w: PL.UW, h: shelfH }); continue; }
            var left = a3full - (s - a2) * 2;
            var cnt = Math.max(0, Math.min(2, left));
            for (var j = 0; j < 2; j++) {
                cells.push({ k: j < cnt ? 'A3v' : 'free', x: x + j * PL.UW / 2, y: y, w: PL.UW / 2, h: shelfH });
            }
        }
        var hx = (a1 + restCols) * PL.UW;
        for (var h = 0; h < half * 2; h++) {
            cells.push({ k: h < a3half ? 'A3v' : 'free', x: hx, y: h * shelfH, w: PL.UW / 2, h: shelfH });
        }
        var used = (c.A1v || 0) - over.A1v + ((c.A2s || 0) - over.A2s) * 0.5 + ((c.A3v || 0) - over.A3v) * 0.25;
        return { cells: cells, over: over, width: n * PL.UW, used: used, cap: n };
    }

    function plochyLayoutBottom(m, c) {
        var cells = [], over = { A2v: 0, A1s: 0, A3s: 0 };
        var a2 = Math.min(c.A2v || 0, m); over.A2v = (c.A2v || 0) - a2;
        var rest = m - a2;
        var a1 = Math.min(c.A1s || 0, Math.floor(rest / 2)); over.A1s = (c.A1s || 0) - a1;
        rest -= a1 * 2;
        var a3 = Math.min(c.A3s || 0, rest * 2); over.A3s = (c.A3s || 0) - a3;
        var x = 0, i;
        for (i = 0; i < a2; i++, x += PL.VW) { cells.push({ k: 'A2v', x: x, y: 0, w: PL.VW, h: PL.VH }); }
        for (i = 0; i < a1; i++, x += 2 * PL.VW) { cells.push({ k: 'A1s', x: x, y: 0, w: 2 * PL.VW, h: PL.VH }); }
        for (i = 0; i < rest; i++, x += PL.VW) {
            var left = a3 - i * 2, cnt = Math.max(0, Math.min(2, left));
            for (var j = 0; j < 2; j++) {
                cells.push({ k: j < cnt ? 'A3s' : 'free', x: x, y: j * PL.VH / 2, w: PL.VW, h: PL.VH / 2 });
            }
        }
        var used = a2 + a1 * 2 + Math.ceil(a3 / 2);
        return { cells: cells, over: over, width: m * PL.VW, used: used, cap: m };
    }

    function plochySvg(top, bot, items) {
        var W = Math.max(top.width, bot.width, 1), H = PL.UH + PL.VH;
        var ns = 'http://www.w3.org/2000/svg', xh = 'http://www.w3.org/1999/xhtml';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
        svg.setAttribute('class', 'kp-plochy-svg');
        svg.style.width = 'min(100%, ' + Math.round(W * 1.7) + 'px)';
        var pocitadlo = {};
        function add(cells, dy) {
            cells.forEach(function (c) {
                var r = document.createElementNS(ns, 'rect');
                r.setAttribute('x', c.x); r.setAttribute('y', c.y + dy);
                r.setAttribute('width', c.w); r.setAttribute('height', c.h);
                r.setAttribute('class', 'kp-pl-cell kp-pl-' + c.k);
                svg.appendChild(r);
                if (c.k === 'free') { return; }
                var i = pocitadlo[c.k] = (pocitadlo[c.k] || 0);
                pocitadlo[c.k]++;
                var it = (items[c.k] || [])[i] || {};
                var text = (it.o ? it.o : '') + (it.a ? ': ' + it.a : '');
                var fs = c.w >= 99 ? 8 : (c.w >= 70 ? 7 : 5.6);
                var lines = Math.max(1, Math.floor((c.h - 4) / (fs * 1.2)));
                var fo = document.createElementNS(ns, 'foreignObject');
                fo.setAttribute('x', c.x); fo.setAttribute('y', c.y + dy);
                fo.setAttribute('width', c.w); fo.setAttribute('height', c.h);
                var d = document.createElementNS(xh, 'div');
                d.setAttribute('class', 'kp-pl-txt');
                d.setAttribute('title', PLOCHY_NAZVY[c.k] + ' — ' + text);
                d.style.fontSize = fs + 'px';
                var orgLines = lines >= 5 ? 2 : 1;
                var inner = document.createElementNS(xh, 'div');
                inner.setAttribute('class', 'kp-pl-txt-in');
                var oEl = document.createElementNS(xh, 'div');
                oEl.setAttribute('class', 'kp-pl-org');
                oEl.style.webkitLineClamp = orgLines;
                oEl.textContent = it.o || PLOCHY_NAZVY[c.k];
                inner.appendChild(oEl);
                if (it.a) {
                    var aEl = document.createElementNS(xh, 'div');
                    aEl.setAttribute('class', 'kp-pl-akcia');
                    aEl.style.webkitLineClamp = Math.max(1, lines - orgLines);
                    aEl.textContent = it.a;
                    inner.appendChild(aEl);
                }
                d.appendChild(inner);
                fo.appendChild(d);
                svg.appendChild(fo);
            });
        }
        add(top.cells, 0);
        add(bot.cells, PL.UH);
        return svg;
    }

    function fmtNum(n) { return (Math.round(n * 100) / 100).toString().replace('.', ','); }

    function renderPlochyDetail(plochaId) {
        var p = (KP.plochy || []).filter(function (x) { return x.id === plochaId; })[0];
        var $detail = $('#kp-plochy-detail').empty();
        if (!p) { return; }

        var items = (plochyObsadenost && plochyObsadenost[plochaId]) || {};
        var c = {};
        ['A1v', 'A2s', 'A3v', 'A2v', 'A3s', 'A1s', 'A4'].forEach(function (k) { c[k] = (items[k] || []).length; });

        $detail.append($('<div class="kp-plochy-rect-title"></div>').text(p.cislo + '. ' + (p.nazov || '')));

        if (!p.topUnits && !p.bottomUnits) {
            $detail.append($('<div class="kp-plochy-no-sloty"></div>').text('Táto plocha ešte nemá nastavené rozloženie — nastavte ho v Administrácia → Plochy.'));
            return;
        }

        var top = plochyLayoutTop(p.topUnits || 0, c);
        var bot = plochyLayoutBottom(p.bottomUnits || 0, c);

        // Súhrn: využitie radov + počty podľa formátu/orientácie
        var $sum = $('<div class="kp-plochy-sum"></div>');
        $sum.append($('<span class="kp-plochy-sum-item"></span>').text('Horný rad: ' + fmtNum(top.used) + ' / ' + fmtNum(top.cap) + ' modulov'));
        $sum.append($('<span class="kp-plochy-sum-item"></span>').text('Spodný rad: ' + fmtNum(bot.used) + ' / ' + fmtNum(bot.cap) + ' modulov'));
        $detail.append($sum);

        var $cnt = $('<div class="kp-plochy-sum"></div>');
        ['A1v', 'A2s', 'A3v', 'A2v', 'A3s', 'A1s', 'A4'].forEach(function (k) {
            if (!c[k]) { return; }
            var pretecene = (top.over[k] || 0) + (bot.over[k] || 0);
            var $b = $('<span class="kp-plochy-sum-item"></span>').text(PLOCHY_NAZVY[k] + ': ' + c[k] + (k === 'A4' ? ' (mimo schémy)' : ''));
            if (pretecene) { $b.addClass('kp-plochy-over').text(PLOCHY_NAZVY[k] + ': ' + c[k] + ' — nezmestí sa ' + pretecene); }
            $cnt.append($b);
        });
        if ($cnt.children().length) { $detail.append($cnt); }

        $detail.append($('<div class="kp-plochy-board"></div>').append(plochySvg(top, bot, items)));
    }

    function setActiveTab(name) {
        $('#kp-tabs .kp-tab').removeClass('active').filter('[data-tab="' + name + '"]').addClass('active');
    }
    function openPlochyTab() {
        if (plochyTabOpen) { return; }
        plochyTabOpen = true;
        setActiveTab('plochy');
        $('#kp-app').addClass('kp-view-plochy');
        if (!$('#kp-plochy-selector').children().length) { renderPlochySelector(); }
        fetchObsadenost(function () { renderPlochyDetail(plochySelectedId); });
    }
    function closePlochyTab() {
        plochyTabOpen = false;
        setActiveTab('tabulka');
        $('#kp-app').removeClass('kp-view-plochy');

    }

    /* ===== INIT ===== */
    $(function () {
        var $tbody = $('#kp-tbody');

        function initRow($r) {
            initAC($r.find('.kp-ac-wrap'));
            initDateShort($r);
            recalcRow($r);
        }

        // Init existujúcich riadkov
        $tbody.find('.kp-row').each(function () { initRow($(this)); });

        var t0Init = (window.performance && performance.now) ? performance.now() : 0;
        if (window.console) { console.info('[kass-vylep] stránka začala spracovanie v ' + Math.round(t0Init) + ' ms od otvorenia, prvé riadky pripravené'); }

        // Staršie riadky (v <template>) sa doplnia po dávkach na pozadí, aby sa stránka dala používať hneď
        var rowsLoading = false;
        (function loadOlderRows() {
            var tpl = document.getElementById('kp-older-rows');
            if (!tpl) { return; }
            var pending = Array.prototype.slice.call(tpl.content.children);
            var total = pending.length + $tbody.find('.kp-row').length;
            var CHUNK = 45;
            rowsLoading = true;
            $('#kp-sum-row').addClass('kp-sum-loading');
            $('#kp-count').text(total + ' riadkov');
            function finish() {
                if (window.console) { console.info('[kass-vylep] všetkých ' + total + ' riadkov hotových po ' + Math.round(performance.now()) + ' ms'); }
                rowsLoading = false;
                $(tpl).remove();
                $('#kp-sum-row').removeClass('kp-sum-loading');
                if ($('#kp-filter-org').val() || $('#kp-filter-mes').val() || $('#kp-filter-platba').val()) { applyFilter(); }
                else { $('#kp-count').text($tbody.find('.kp-row').length + ' riadkov'); recalcTotals(); }
            }
            function step() {
                var chunk = pending.splice(Math.max(0, pending.length - CHUNK), CHUNK);
                var frag = document.createDocumentFragment();
                chunk.forEach(function (tr) { frag.appendChild(tr); });
                tbody0.insertBefore(frag, tbody0.firstChild);
                chunk.forEach(function (tr) { var $r = $(tr); initRow($r); plochyState($r); });
                $('#kp-count').text(total + ' riadkov');
                if (pending.length) { setTimeout(step, 25); } else { finish(); }
            }
            var tbody0 = $tbody[0];
            setTimeout(step, 30);
        })();

        recalcTotals();
        $('#kp-count').text($tbody.find('.kp-row').length + ' riadkov');

        // Pridať riadok
        $('#kp-add-row').on('click', function () {
            if (rowsLoading) { status('Načítavam staršie záznamy…', false); return; }
            var num = $tbody.find('.kp-row').length + 1;
            var $tr = $(newRowHtml(num));
            $tbody.append($tr);
            initAC($tr.find('.kp-ac-wrap'));
            initDateShort($tr);
            recalcRow($tr);
            plochyState($tr);
            $tr.find('.kp-akcia').focus();
            $tr[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        // Zmeny v riadku
        $tbody.on('change', '.kp-format, .kp-kusy, .kp-tlac, .kp-ine', function () {
            recalcRow($(this).closest('tr')); recalcTotals();
            scheduleSave($(this).closest('tr'));
        });

        // Automatické ukladanie pri písaní (okrem organizácie — tá sa ukladá
        // až po výbere zo zoznamu, aby sa neukladali rozpísané názvy)
        $tbody.on('input', '.kp-inp:not(.kp-ac-input)', function () {
            scheduleSave($(this).closest('tr'));
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
        $tbody.on('change', '.kp-tyzdne', function () { syncDates($(this).closest('tr'), 'tyzdne'); recalcRow($(this).closest('tr')); recalcTotals(); scheduleSave($(this).closest('tr')); });

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
            if (!$drop.children().length) { $drop.html(FMT_OPTS_HTML); }
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
            scheduleSave($(this).closest('tr'));
        });
        $(document).on('click', function () { $('.kp-fmt-drop').removeClass('open'); });

        $tbody.on('input change', '.kp-kusy', function () { plochyState($(this).closest('tr')); });
        $tbody.find('.kp-row').each(function () { plochyState($(this)); });

        // Orientácia plagátu (na výšku / na šírku)
        $tbody.on('click', '.kp-orient-btn', function (e) {
            e.stopPropagation();
            var $b = $(this);
            var sirka = $b.attr('data-val') === 'v';
            $b.attr('data-val', sirka ? 's' : 'v')
              .attr('title', (sirka ? 'Na šírku' : 'Na výšku') + ' — klik pre zmenu');
            $b.siblings('.kp-orient').val(sirka ? 's' : 'v');
            scheduleSave($b.closest('tr'));
        });

        // Platba dropdown — otvoriť/zatvoriť
        $tbody.on('click', '.kp-platba-btn', function (e) {
            e.stopPropagation();
            var $btn  = $(this);
            var $drop = $btn.siblings('.kp-platba-drop');
            if (!$drop.children().length) { $drop.html(PLATBA_OPTS_HTML); }
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
            scheduleSave($(this).closest('tr'));
        });
        // Zatvoriť kliknutím mimo
        $(document).on('click', function () { $('.kp-platba-drop').removeClass('open'); });

        // Kusy + plochy — modal (rovnaký vzor ako Tlač)
        var $plochyRow   = null;
        var $plochyModal = $('#kp-plochy-modal');
        var plochyMode   = 'vsetky';
        var $kusyInp     = $('#kp-plochy-kusy');

        function plochyUpdateCount() {
            var n    = plochyMode === 'vyber' ? $('#kp-plochy-picker-grid input:checked').length : plochyCount(plochyMode, '');
            var kusy = parseInt($kusyInp.val(), 10) || 0;
            var ok   = n > 0 && n === kusy;
            $('#kp-plochy-count').toggleClass('kp-plochy-count-warn', !ok)
                .text('Vybrané plochy: ' + n + ' · Kusy: ' + kusy + (ok ? ' ✓' : ' — počet kusov a plôch sa nezhoduje'));
        }
        function plochySetMode(mode) {
            plochyMode = mode;
            $('#kp-plochy-modes .kp-plochy-mode-btn').removeClass('active').filter('[data-val="' + mode + '"]').addClass('active');
            $('#kp-plochy-picker-grid').toggle(mode === 'vyber');
            plochyUpdateCount();
        }
        function plochyOpen($tr) {
            $plochyRow = $tr;
            var mode  = $tr.find('.kp-plochy-sposob').val() || 'vsetky';
            var vybrane = ($tr.find('.kp-plochy-vyber').val() || '').split(',').filter(function (s) { return s; });
            var $grid = $('#kp-plochy-picker-grid').empty();
            (KP.plochy || []).forEach(function (p) {
                var $lbl = $('<label class="kp-plochy-picker-item"></label>');
                $('<input type="checkbox">').val(p.id).prop('checked', vybrane.indexOf(String(p.id)) !== -1).appendTo($lbl);
                $lbl.append(' ' + p.cislo + '. ' + (p.nazov || ''));
                $grid.append($lbl);
            });
            $kusyInp.val($tr.find('.kp-kusy').val() || '');
            plochySetMode(mode);
            $plochyModal.addClass('open');
            setTimeout(function () { $kusyInp.trigger('focus').trigger('select'); }, 30);
        }
        function plochyClose() { $plochyModal.removeClass('open'); $plochyRow = null; }

        // Zadanie počtu kusov: 26 = všetky plochy; 10/15/20 = zodpovedajúce TOP; inak ručný výber
        $kusyInp.on('input', function () {
            var n = parseInt($(this).val(), 10) || 0;
            var all = (KP.plochy || []).length;
            if (n > 0 && n === all) { plochySetMode('vsetky'); return; }
            if (plochyMode !== 'vyber') {
                var top = 'top' + n;
                if ((n === 10 || n === 15 || n === 20) && plochyCount(top, '') === n) { plochySetMode(top); return; }
                if (n > 0) { plochySetMode('vyber'); return; }
            }
            plochyUpdateCount();
        });
        // Voľba hotového režimu nastaví kusy podľa počtu plôch
        $('#kp-plochy-modes').on('click', '.kp-plochy-mode-btn', function () {
            var mode = $(this).attr('data-val');
            plochySetMode(mode);
            if (mode !== 'vyber') {
                var n = plochyCount(mode, '');
                if (n > 0) { $kusyInp.val(n); plochyUpdateCount(); }
            }
        });
        // Ručný výber plôch nastaví kusy podľa počtu zaškrtnutých
        $('#kp-plochy-picker-grid').on('change', 'input', function () {
            $kusyInp.val($('#kp-plochy-picker-grid input:checked').length);
            plochyUpdateCount();
        });

        $tbody.on('click', '.kp-kusy-btn', function () { plochyOpen($(this).closest('tr')); });
        $('#kp-plochy-cancel').on('click', plochyClose);
        $plochyModal.on('click', function (e) { if (e.target === this) { plochyClose(); } });
        $kusyInp.on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#kp-plochy-ok').trigger('click'); } });
        $('#kp-plochy-ok').on('click', function () {
            if (!$plochyRow) { return; }
            var $row = $plochyRow;
            var csv = '';
            var mode = plochyMode;
            if (mode === 'vyber') {
                var ids = [];
                $('#kp-plochy-picker-grid input:checked').each(function () { ids.push(this.value); });
                if (!ids.length) { mode = 'vsetky'; } else { csv = ids.join(','); }
            }
            var kusy = parseInt($kusyInp.val(), 10) || 0;
            plochyClose();
            $row.find('.kp-plochy-sposob').val(mode);
            $row.find('.kp-plochy-vyber').val(csv);
            $row.find('.kp-kusy').val(kusy).trigger('change');
            plochyState($row);
        });

        /* ===== TLAČ MODAL ===== */
        var $tlacModal = $('#kp-tlac-modal');
        var $tlacRow = null;
        var tlacTC = KP.tlacCennik || {
            A4: { cb: { s: 0.15, bez: 0.1220 }, cb2: { s: 0.30, bez: 0.2439 }, far: { s: 0.70, bez: 0.5691 } },
            A3: { cb: { s: 0.25, bez: 0.2033 }, cb2: { s: 0.50, bez: 0.4065 }, far: { s: 1.40, bez: 1.1382 } }
        };

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
            var total = 0, totalBez = 0;
            $('#kp-tlac-lines .kp-tlac-line').each(function () {
                var fmt   = $(this).find('.tl-fmt').val();
                var typ   = $(this).find('.tl-typ').val();
                var ks    = parseInt($(this).find('.tl-ks').val(), 10) || 1;
                var rate  = (tlacTC[fmt] && tlacTC[fmt][typ]) || { s: 0, bez: 0 };
                var sub   = (rate.s   || 0) * ks;
                var subBez = (rate.bez || 0) * ks;
                $(this).find('.tl-suma').text(sub > 0 ? sub.toFixed(2).replace('.', ',') + ' €' : '0,00 €');
                total += sub;
                totalBez += subBez;
            });
            $('#kp-tlac-cena').text(total > 0 ? total.toFixed(2).replace('.', ',') + ' €' : '—');
            return { s: total, bez: totalBez };
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
                $tlacRow.find('.kp-tlac-bez').val(0);
                recalcRow($tlacRow);
                recalcTotals();
                scheduleSave($tlacRow);
            }
            $tlacModal.removeClass('open');
        });

        $('#kp-tlac-ok').on('click', function () {
            if (!$tlacRow) { return; }
            var totals = tlacRecalc();
            var lines = [];
            $('#kp-tlac-lines .kp-tlac-line').each(function () {
                lines.push({ fmt: $(this).find('.tl-fmt').val(), typ: $(this).find('.tl-typ').val(), ks: $(this).find('.tl-ks').val() });
            });
            $tlacRow.data('tlac-lines', lines);
            var formatted = totals.s > 0 ? totals.s.toFixed(2).replace('.', ',') + ' €' : '';
            $tlacRow.find('.kp-tlac').val(formatted);
            $tlacRow.find('.kp-tlac-bez').val(totals.bez.toFixed(2));
            recalcRow($tlacRow);
            recalcTotals();
            scheduleSave($tlacRow);
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

            $('#kp-tbody .kp-row').filter(isRowShown).each(function () {
                var $tr   = $(this);
                var doVal  = $tr.find('.kp-do').val();
                var platba = $tr.find('.kp-platba-hidden').val();
                var fmt    = $tr.find('.kp-format').val();
                var org    = $tr.attr('data-org') || $tr.find('.kp-ac-input').val() || '';
                var akcia  = $tr.find('.kp-akcia').val() || '';
                var od     = $tr.find('.kp-od').val();
                if (!doVal) { return; }
                var platbaSkr = PLATBA_TO_SKR[platba] || platba;
                var isPaid = (platbaSkr === 'H' || platbaSkr === 'FA' || platbaSkr === 'K');
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

        // Záložka Plochy
        $('#kp-tabs').on('click', '.kp-tab', function () {
            if ($(this).attr('data-tab') === 'plochy') { openPlochyTab(); } else { closePlochyTab(); }
        });
        $('#kp-plochy-selector').on('click', '.kp-plochy-sel-btn', function () {
            plochySelectedId = parseInt($(this).attr('data-id'), 10);
            updateSelectorActive();
            renderPlochyDetail(plochySelectedId);
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
            if (plochyTabOpen) { return; }           // schéma plôch si scrolluje samostatne
            if (wrap.contains(e.target)) { return; } // vnútri tabuľky funguje natívne
            if (e.target.closest && e.target.closest('.kp-modal-overlay, .kp-tlac-modal, .kp-ac-drop, .kp-platba-drop, .kp-fmt-drop, .flatpickr-calendar')) { return; }
            wrap.scrollTop += e.deltaY;
        }, { passive: true });
    });

})(jQuery);
