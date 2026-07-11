(function ($) {
    'use strict';

    /* ============ Formulár výlepu: doplnenie ceny, výpočet dátumu Do, suma ============ */
    function initVylepForm() {
        var $cena = $('#ars-cena');
        if (!$cena.length) { return; }

        function prepocitajSumu() {
            var kusy = parseFloat($('#ars-kusy').val()) || 0;
            var cena = parseFloat(($cena.val() || '0').replace(',', '.')) || 0;
            var suma = (kusy * cena).toFixed(2).replace('.', ',');
            $('#kass-vylep-suma').text(suma + ' €');
        }

        function aktualizujDatumDo() {
            var od = $('#ars-datum-od').val();
            var tyzdne = parseInt($('#ars-tyzdne').val(), 10) || 1;
            if (!od) { return; }
            var d = new Date(od);
            d.setDate(d.getDate() + tyzdne * 7);
            var info = d.toLocaleDateString('sk-SK');
            $('#ars-datum-do-info').text('Koniec výlepu: ' + info);
        }

        // Doplniť cenu z cenníka cez AJAX
        $('#ars-doplnit-cenu').on('click', function () {
            var format = $('#kass-format').val();
            var tyzdne = parseInt($('#ars-tyzdne').val(), 10) || 1;
            var kategoria = $('#kass-cennik-typ').val() || 'vylep';
            $.get(KASSVylep.ajax, {
                action: 'kass_cena', nonce: KASSVylep.nonce, kategoria: kategoria, format: format, tyzdne: tyzdne
            }, function (res) {
                if (res && res.success) {
                    $cena.val(parseFloat(res.data.cena).toFixed(4));
                    prepocitajSumu();
                }
            });
        });

        $('#ars-kusy, #ars-cena').on('input', prepocitajSumu);
        $('#ars-tyzdne, #ars-datum-od').on('input change', aktualizujDatumDo);
        $('#kass-format, #ars-tyzdne, #kass-cennik-typ').on('change', function () {
            // automaticky doplní cenu pri zmene formátu/týždňov/typu
            $('#ars-doplnit-cenu').trigger('click');
        });

        // pri výbere organizácie z DB doplň názov do textového poľa
        $('#ars-org-select').on('change', function () {
            var nazov = $(this).find('option:selected').data('nazov');
            if (nazov) { $('#ars-org-nazov').val(nazov); }
        });

        prepocitajSumu();
        aktualizujDatumDo();
    }

    /* ============ Generátor podkladu k fakturácii ============ */
    function initFaktura() {
        var $doc = $('#fakt-doc');
        if (!$doc.length || !window.KASSFakt) { return; }

        var F = window.KASSFakt;
        var rows = [];
        var rid = 0;

        function f4(n) { return Number(n).toFixed(4).replace('.', ','); }
        function f2(n) { return Number(n).toFixed(2).replace('.', ','); }

        function cenaZCennika(fmt, oi) {
            var tyzdne = oi + 1;
            var v = F.cennik.vylep && F.cennik.vylep[fmt];
            if (!v) { return 0; }
            var total = 0;
            while (tyzdne > 0) {
                var t = Math.min(5, tyzdne);
                total += parseFloat(v[t] && v[t].bez || 0);
                tyzdne -= t;
            }
            return total;
        }

        function f2r(n) { return Number(n).toFixed(2).replace('.', ','); }
        function f4r(n) { return Number(n).toFixed(4).replace('.', ','); }

        function skratTyzden(oi) {
            return F.obdobia[oi] || (oi + 1) + 'T';
        }

        function datumSK(iso) {
            if (!iso) return '';
            var p = iso.split('-'); if (p.length < 3) return iso;
            return parseInt(p[2], 10) + '.' + parseInt(p[1], 10) + '.' + p[0];
        }

        function addRow(data) {
            data = data || {};
            rows.push({
                id: rid++,
                popis: data.popis || 'Výlep',
                fmt:   data.fmt   || 'A3',
                oi:    (data.oi != null) ? data.oi : 0,
                kusy:  data.kusy  || 1,
                cena:  (data.cena != null) ? data.cena : null,
                od:    data.od    || '',
                do:    data.do    || ''
            });
            render();
        }
        function rmRow(id) { rows = rows.filter(function (r) { return r.id !== id; }); render(); }

        function render() {
            var $tbody = $('#fakt-polozky').empty();
            rows.forEach(function (r, idx) {
                if (r.cena == null) { r.cena = cenaZCennika(r.fmt, r.oi); }
                var sub = r.cena * r.kusy;
                var isLast = idx === rows.length - 1;

                var $tr = $('<tr></tr>');
                if (idx > 0) { $tr.addClass('fa4-item-sep'); }

                // Ľavá časť
                var $tdL = $('<td class="fa4-item-l"></td>');

                // Hlavička — názov s tlačidlom mazania
                var $head = $('<div class="fa4-item-head"></div>');
                var $popis = $('<input type="text">').val(r.popis).on('input', function () { r.popis = this.value; });
                var $del = $('<button type="button" class="fa4-item-del kass-noprint">✕ Odstrániť</button>').on('click', function () { rmRow(r.id); });
                $head.append($popis, $del);

                // Formát / počet kusov — plain text s kliknutím na úpravu
                var $rf = $('<div class="fa4-row"></div>').append('<b>Formát / počet kusov:</b>');
                var $sf = $('<select class="faf-sel faf-hidden-sel" style="display:none;"></select>');
                F.formaty.forEach(function (f) { $('<option>').text(f).prop('selected', f === r.fmt).appendTo($sf); });
                $sf.on('change', function () { r.fmt = this.value; r.cena = cenaZCennika(r.fmt, r.oi); render(); });
                var $kus = $('<input type="number" min="1" style="width:40px;display:none;" class="faf">').val(r.kusy).on('change', function () {
                    r.kusy = Math.max(1, parseInt(this.value, 10) || 1); render();
                });
                var $txt = $('<span class="fa4-plain-txt" style="cursor:pointer;" title="Klikni pre úpravu"></span>')
                    .text(r.fmt + ' / ' + r.kusy + ' ks')
                    .on('click', function () {
                        $txt.hide(); $sf.show().focus(); $kus.show();
                    });
                $sf.on('blur', function () { setTimeout(function () { $txt.text(r.fmt + ' / ' + r.kusy + ' ks').show(); $sf.hide(); $kus.hide(); }, 200); });
                $kus.on('blur', function () { setTimeout(function () { $txt.text(r.fmt + ' / ' + r.kusy + ' ks').show(); $sf.hide(); $kus.hide(); }, 200); });
                $rf.append($txt, $sf, $('<span> / </span>').hide(), $kus, $('<span> ks</span>').hide());

                // Výlep — plain text formát: dd.m. – dd.m. rrrr (n týždňov)
                var $rv = $('<div class="fa4-row"></div>').append('<b>Výlep:</b>');

                function formatDatumVylep(od, doo, oi) {
                    var tLabel = F.obdobia[oi] || (oi+1) + 'T';
                    if (!od) { return '<span style="color:#aaa;">neurčený dátum</span> (' + tLabel + ')'; }
                    function dm(iso) {
                        var p = iso.split('-');
                        return parseInt(p[2],10) + '.' + parseInt(p[1],10) + '.';
                    }
                    var rok = od.split('-')[0];
                    var odStr = dm(od);
                    var doStr = doo ? (dm(doo) + ' ' + rok) : '?';
                    return odStr + ' \u2013 ' + doStr + ' \u00a0(' + tLabel + ')';
                }

                var $vTxt = $('<span class="fa4-plain-txt" style="cursor:pointer;" title="Klikni pre úpravu"></span>')
                    .html(formatDatumVylep(r.od, r.do, r.oi));
                var $odI = $('<input type="date" class="faf" style="display:none;">').val(r.od);
                var $doI = $('<input type="date" class="faf" style="display:none;">').val(r.do);
                var $soI = $('<select class="faf-sel" style="display:none;width:90px;"></select>');
                F.obdobia.forEach(function (o, i) { $('<option>').attr('value', i).text(o).prop('selected', i === r.oi).appendTo($soI); });

                function commitDates() {
                    r.od = $odI.val(); r.do = $doI.val();
                    r.oi = parseInt($soI.val(), 10);
                    r.cena = cenaZCennika(r.fmt, r.oi);
                    $vTxt.html(formatDatumVylep(r.od, r.do, r.oi)).show();
                    $odI.hide(); $doI.hide(); $soI.hide();
                    prepocitaj();
                }
                $vTxt.on('click', function () { $vTxt.hide(); $odI.show(); $doI.show(); $soI.show(); $odI.focus(); });
                $odI.on('blur', function () { setTimeout(commitDates, 200); });
                $doI.on('blur', function () { setTimeout(commitDates, 200); });
                $soI.on('change', commitDates);

                $rv.append($vTxt, $odI, $(' – '), $doI, $soI);

                // Výpočet — plain text, no bold, aligned
                var $rc = $('<div class="fa4-row" style="margin-bottom:0;"></div>').append('<b>Výpočet:</b>');
                $rc.append(
                    $('<span>').text(r.kusy + ' × ' + f4r(r.cena) + ' = ' + f2r(r.cena * r.kusy) + ' €')
                );
                $tdL.append($head, $rf, $rv, $rc);

                var $trMain = $('<tr></tr>');
                if (idx > 0) { $trMain.addClass('fa4-item-sep'); }
                var $tdR = $('<td class="fa4-item-r" style="border-left:1.5px solid #000;border-bottom:none;vertical-align:top;padding:6px 0 0 0;text-align:center;font-size:9pt;font-weight:bold;"></td>');
                if (idx === 0) {
                    $tdR.html('<div style="border-bottom:1px solid #000;padding-bottom:3px;margin:0 4px;">Cena v €</div>');
                }
                $trMain.append($tdL, $tdR);
                $tbody.append($trMain);

                // Spodný riadok: cena bez DPH | suma €
                var $trBot = $('<tr></tr>');
                var $tdBotL = $('<td style="padding:0 8px 4px 8px; border-top:none;"></td>')
                    .html('<div class="fa4-row" style="margin-bottom:0;margin-top:0;"><b style="min-width:140px;font-weight:normal;white-space:nowrap;"></b><span style="font-size:9pt;">cena bez DPH</span></div>');
                var $tdBotR = $('<td style="text-align:center; padding:0 8px 4px 8px; font-size:9pt; border-left:1.5px solid #000; border-top:none;"></td>').text(f2r(sub) + ' €');
                $trBot.append($tdBotL, $tdBotR);
                $tbody.append($trBot);

                // Prázdny riadok pod cena bez DPH
                var $trEmpty = $('<tr></tr>');
                $trEmpty.append($('<td style="padding:10px 8px; border-top:none;"></td>'));
                $trEmpty.append($('<td style="border-left:1.5px solid #000; border-top:none;"></td>'));
                $tbody.append($trEmpty);
            });

            // Vždy celkovo max 3 bloky (vyplnené + prázdne)
            var phCount = Math.max(0, 3 - rows.length);
            for (var p = 0; p < phCount; p++) {
                var $ph = $('<tr class="fa4-placeholder"></tr>');
                var $phL = $('<td class="fa4-ph-cell fa4-item-l" style="min-height:145px;height:145px;"></td>')
                    .html('<div style="display:flex;align-items:center;justify-content:flex-start;height:100%;padding:4px 0;"><span class="fa4-ph-btn">+ Pridať ďalší výlep</span></div>');
                var $phR = $('<td class="fa4-item-r" style="min-height:145px;height:145px;"></td>');
                $phL.on('click', function () { addRow(); });
                $ph.append($phL, $phR);
                $tbody.append($ph);
            }
            prepocitaj();
        }

        function prepocitaj() {
            var total = rows.reduce(function (s, r) { return s + (r.cena || 0) * r.kusy; }, 0);
            $('#fakt-suma').text(f2r(total) + ' €');
        }

        function nacitajOrg(id) {
            var o = F.org[id] || F.org[parseInt(id, 10)] || F.org[String(id)];
            if (!o) {
                console.warn('KASSFakt: org not found for id=' + id, 'Available:', Object.keys(F.org));
                return;
            }

            function setVal(sel, val) {
                var el = document.getElementById(sel.replace('#', ''));
                if (!el) { return; }
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.value = val || '';
                } else {
                    el.textContent = val || '';
                }
            }

            setVal('#f-nazov',  o.nazov  || '');
            setVal('#f-ulica',  o.ulica  || '');
            setVal('#f-mesto',  o.mesto  || '');
            setVal('#f-ico',    o.ico    || '');
            setVal('#f-dic',    o.dic    || '');
            setVal('#f-icdph',  o.icdph  || '');
            setVal('#f-penaz',  o.penaz  || '');
            setVal('#f-iban',   o.iban   || '');
        }



        // udalosti
        $('#fakt-org').on('change', function () { nacitajOrg(this.value); });
        $('#fakt-pridat, #fakt-additem').on('click', function () { addRow(); });

        // init — predvyplnenie z URL parametra
        var dnes = new Date().toISOString().slice(0, 10);

        // Načítaj odberateľa — z URL parametra alebo z hodnoty selectu, alebo z názvu v položke
        var initOrgId = F.predvybrany || parseInt($('#fakt-org').val(), 10);

        // Ak stále 0, skús nájsť podľa mena z prvej položky
        if (!initOrgId && F.init && F.init.length && F.init[0].org_nazov) {
            var orgNazov = F.init[0].org_nazov;
            $.each(F.org, function(id, o) {
                if (o.nazov === orgNazov || o.odberatel === orgNazov) {
                    initOrgId = parseInt(id, 10);
                    return false;
                }
            });
        }

        if (initOrgId) {
            $('#fakt-org').val(initOrgId);
            nacitajOrg(initOrgId);
        }
        if (F.init && F.init.length) {
            F.init.forEach(function (p) { addRow(p); });
        } else {
            addRow();
        }
    }

    $(function () {
        initVylepForm();
        initFaktura();
    });

})(jQuery);