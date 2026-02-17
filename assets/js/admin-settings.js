/* ─── Video Bubble – Admin Settings JS ───────────────────────────────────── */

(function ($) {
    'use strict';

    var $rulesContainer = $('#vb-video-rules');
    var $rulesInput = $('#vb_video_rules');
    var wpPages = (window.vbAdmin && window.vbAdmin.pages) ? window.vbAdmin.pages : [];
    var rules = [];

    // Parse existing rules
    try {
        rules = JSON.parse($rulesInput.val() || '[]');
    } catch (e) {
        rules = [];
    }

    function renderRules() {
        $rulesContainer.empty();
        if (rules.length === 0) {
            addRule('*', '');
            return;
        }
        rules.forEach(function (rule, i) {
            $rulesContainer.append(buildRow(rule.pages || '*', rule.video || '', i));
        });
        syncInput();
    }

    function buildRow(pages, video, index) {
        var $row = $('<div class="vb-rule-row"></div>');

        // Pages selector
        var $pagesWrap = $('<div class="vb-rule-pages-wrap"></div>');
        var isAll = (pages === '*');
        var selectedIds = isAll ? [] : pages.split(',').map(function (s) { return s.trim(); });

        // Toggle: All pages vs specific
        var $toggle = $('<select class="vb-rule-scope"></select>');
        $toggle.append('<option value="*"' + (isAll ? ' selected' : '') + '>All Pages</option>');
        $toggle.append('<option value="specific"' + (!isAll ? ' selected' : '') + '>Specific Pages</option>');

        // Multi-select for specific pages
        var $pageSelect = $('<select class="vb-rule-page-select" multiple></select>');
        wpPages.forEach(function (p) {
            var sel = selectedIds.indexOf(String(p.id)) !== -1 ? ' selected' : '';
            $pageSelect.append('<option value="' + p.id + '"' + sel + '>' + escHtml(p.title) + ' (/' + escHtml(p.slug) + ')</option>');
        });

        if (isAll) {
            $pageSelect.hide();
        }

        $toggle.on('change', function () {
            if ($(this).val() === '*') {
                $pageSelect.hide();
                rules[index].pages = '*';
            } else {
                $pageSelect.show();
                updatePagesFromSelect(index, $pageSelect);
            }
            syncInput();
        });

        $pageSelect.on('change', function () {
            updatePagesFromSelect(index, $(this));
            syncInput();
        });

        $pagesWrap.append($toggle).append($pageSelect);

        // Video URL input
        var $video = $('<input type="url" class="vb-rule-video" placeholder="Video URL (Bunny embed or .mp4)" />')
            .val(video)
            .on('input', function () {
                rules[index].video = $(this).val();
                syncInput();
            });

        // Remove button
        var $remove = $('<button type="button" class="vb-rule-remove" title="Remove rule">&times;</button>')
            .on('click', function () {
                rules.splice(index, 1);
                renderRules();
            });

        $row.append($pagesWrap).append($video).append($remove);
        return $row;
    }

    function updatePagesFromSelect(index, $select) {
        var vals = $select.val();
        if (vals && vals.length > 0) {
            rules[index].pages = vals.join(',');
        } else {
            rules[index].pages = '*';
        }
    }

    function addRule(pages, video) {
        rules.push({ pages: pages || '*', video: video || '' });
        renderRules();
    }

    function syncInput() {
        $rulesInput.val(JSON.stringify(rules));
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Init
    renderRules();

    $('#vb-add-rule').on('click', function () {
        addRule('*', '');
    });

    // Color picker
    $('.vb-color-picker').wpColorPicker();

})(jQuery);
