// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Curriculum map block - AMD module.
 *
 * Written in classic AMD (define()/require()) rather than ES modules
 * (import/export). Reason: this deployment serves amd/src/*.js directly to
 * RequireJS instead of going through a grunt-built amd/build/*.min.js, and
 * RequireJS cannot execute ES import/export syntax on its own - only real
 * define() calls. amd/build/curriculummap(.min).js are kept byte-identical
 * to this file for now; if a real `npx grunt amd` build gets set up later,
 * this file can be rewritten to modern ES module syntax.
 *
 * The cross-tab engine here is a container-scoped port of the standalone
 * HTML prototype (curriculum-map-prototype.html, see project files): same
 * axis-registry / matrix / sidebar model, but the axis registry is now
 * built dynamically from block_curriculummap_get_data's response instead of
 * hardcoded dummy data, and every DOM query is scoped under the block
 * instance's own container (data-role attributes, not ids) so multiple
 * instances can coexist on one page.
 *
 * @module     block_curriculummap/curriculummap
 * @copyright  2026 (design collaboration)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/pending', 'core/str'], function(Ajax, Notification, Pending, Str) {

    var SELECTORS = {
        MODAL_TRIGGER: '[data-region="curriculummap-modal-trigger"]'
    };

    /**
     * Fetch the subjects[] + axis registry intermediate data model.
     *
     * @return {Promise}
     */
    var fetchData = function() {
        var request = Ajax.call([{
            methodname: 'block_curriculummap_get_data',
            args: {}
        }]);
        return request[0];
    };

    // ---------------------------------------------------------------
    // Colour palette - vivid oklch hues assigned to major/group labels
    // in order of first appearance, since server-provided group labels
    // are arbitrary strings (unlike the prototype's fixed DP/core palette).
    // ---------------------------------------------------------------
    var PALETTE = [
        'oklch(55% 0.15 250)', 'oklch(60% 0.17 45)', 'oklch(58% 0.14 145)',
        'oklch(56% 0.16 305)', 'oklch(62% 0.15 85)', 'oklch(56% 0.13 205)',
        'oklch(56% 0.17 18)', 'oklch(58% 0.13 165)', 'oklch(58% 0.12 125)',
        'oklch(58% 0.14 300)', 'oklch(58% 0.16 340)', 'oklch(58% 0.14 40)',
        'oklch(56% 0.13 85)'
    ];

    /**
     * @return {function(string): string} call with a key, get a stable colour back.
     */
    var makeColorAssigner = function() {
        var map = {};
        var next = 0;
        return function(key) {
            if (!key) {
                return 'var(--cmviz-accent)';
            }
            if (!Object.prototype.hasOwnProperty.call(map, key)) {
                map[key] = PALETTE[next % PALETTE.length];
                next++;
            }
            return map[key];
        };
    };

    // ---------------------------------------------------------------
    // Axis registry - built from the server's axes[] (dp/core/milestone,
    // whichever are configured) plus a built-in single-valued "category"
    // axis read straight off subject.category.
    // ---------------------------------------------------------------

    /**
     * @param {Array} axesFromServer
     * @param {Array} subjects
     * @param {Object} strings Prefetched UI strings, keyed by lang string id.
     * @return {{AXES: Object, AXIS_ORDER: Array}}
     */
    var buildAxisRegistry = function(axesFromServer, subjects, strings) {
        var AXES = {};
        var AXIS_ORDER = [];
        var getGroupColor = makeColorAssigner();

        var categories = [];
        subjects.forEach(function(s) {
            if (s.category && categories.indexOf(s.category) === -1) {
                categories.push(s.category);
            }
        });
        categories.sort();
        AXES.category = {
            id: 'category',
            label: strings.viz_categoryaxis,
            multi: false,
            allValues: categories,
            getValues: function(s) { return s.category ? [s.category] : []; }
        };
        AXIS_ORDER.push('category');

        axesFromServer.forEach(function(axis) {
            var itemsByIdnumber = {};
            axis.items.forEach(function(item) { itemsByIdnumber[item.idnumber] = item; });

            var groups = [];
            axis.items.forEach(function(item) {
                if (item.group && groups.indexOf(item.group) === -1) {
                    groups.push(item.group);
                }
            });
            var hasGroups = groups.length > 0;

            if (hasGroups) {
                var majorId = axis.id + '_major';
                AXES[majorId] = {
                    id: majorId,
                    label: axis.label + strings.viz_majorsuffix,
                    multi: true,
                    allValues: groups,
                    getValues: (function(axisId) {
                        return function(s) {
                            var link = (s.links || []).filter(function(l) { return l.axisid === axisId; })[0];
                            var idnumbers = link ? link.idnumbers : [];
                            var seen = {};
                            var out = [];
                            idnumbers.forEach(function(idn) {
                                var item = itemsByIdnumber[idn];
                                var g = item ? item.group : '';
                                if (g && !seen[g]) {
                                    seen[g] = true;
                                    out.push(g);
                                }
                            });
                            return out;
                        };
                    })(axis.id),
                    colorOf: (function(axisId) {
                        return function(v) { return getGroupColor(axisId + ':' + v); };
                    })(axis.id)
                };
                AXIS_ORDER.push(majorId);
            }

            AXES[axis.id] = {
                id: axis.id,
                label: axis.label,
                multi: true,
                allValues: axis.items.map(function(item) { return item.idnumber; }),
                labelOf: function(v) { return (itemsByIdnumber[v] && itemsByIdnumber[v].label) || v; },
                getValues: (function(axisId) {
                    return function(s) {
                        var link = (s.links || []).filter(function(l) { return l.axisid === axisId; })[0];
                        return link ? link.idnumbers : [];
                    };
                })(axis.id),
                parentOf: hasGroups ? function(v) { return itemsByIdnumber[v] ? itemsByIdnumber[v].group : ''; } : null,
                parentLabel: hasGroups ? (axis.id + '_major') : null,
                colorOf: hasGroups
                    ? function(v) {
                        var item = itemsByIdnumber[v];
                        return getGroupColor(axis.id + ':' + (item ? item.group : ''));
                    }
                    : function(v) { return getGroupColor(axis.id + ':' + v); }
            };
            AXIS_ORDER.push(axis.id);
        });

        return {AXES: AXES, AXIS_ORDER: AXIS_ORDER};
    };

    // ---------------------------------------------------------------
    // The app itself - one instance per block, entirely scoped under
    // `container` (never touches document-level ids).
    // ---------------------------------------------------------------

    /**
     * @param {HTMLElement} container
     * @param {Array} axesFromServer
     * @param {Array} subjects
     * @param {Object} strings
     * @return {void}
     */
    var createApp = function(container, axesFromServer, subjects, strings) {
        var registry = buildAxisRegistry(axesFromServer, subjects, strings);
        var AXES = registry.AXES;
        var AXIS_ORDER = registry.AXIS_ORDER;

        if (AXIS_ORDER.length === 0) {
            container.innerHTML = '<p class="cmviz-note">' + strings.viz_nomatch + '</p>';
            return;
        }

        var state = {
            rowAxisId: AXIS_ORDER[0],
            colAxisId: AXIS_ORDER.length > 1 ? AXIS_ORDER[1] : AXIS_ORDER[0],
            rowSortDir: 'asc',
            colSortDir: 'asc',
            mode: 'total',
            cellDisplay: 'number',
            selectedCell: null,
            compareSet: {}, // subject id -> true
            filters: {} // axisId -> {value: true, ...}
        };
        var sidebarView = {mode: 'empty'};

        var rowSel = container.querySelector('[data-role="rowAxis"]');
        var colSel = container.querySelector('[data-role="colAxis"]');
        var rowSortToggle = container.querySelector('[data-role="rowSortToggle"]');
        var colSortToggle = container.querySelector('[data-role="colSortToggle"]');
        var swapBtn = container.querySelector('[data-role="swapAxes"]');
        var modeToggle = container.querySelector('[data-role="modeToggle"]');
        var cellDisplayToggle = container.querySelector('[data-role="cellDisplayToggle"]');
        var resetBtn = container.querySelector('[data-role="resetBtn"]');
        var addFilterAxisSel = container.querySelector('[data-role="addFilterAxis"]');
        var addFilterBtn = container.querySelector('[data-role="addFilterBtn"]');
        var filterBlocksEl = container.querySelector('[data-role="filterBlocks"]');
        var statLineEl = container.querySelector('[data-role="statLine"]');
        var tableEl = container.querySelector('[data-role="crossTable"]');
        var sidebarEl = container.querySelector('[data-role="sidebar"]');

        // ---------- helpers ----------
        var fillAxisSelect = function(sel, excludeId) {
            sel.innerHTML = '';
            AXIS_ORDER.forEach(function(id) {
                if (id === excludeId) {
                    return;
                }
                var opt = document.createElement('option');
                opt.value = id;
                opt.textContent = AXES[id].label;
                sel.appendChild(opt);
            });
        };
        var syncSortToggles = function() {
            rowSortToggle.querySelectorAll('button').forEach(function(b) {
                b.classList.toggle('active', b.dataset.dir === state.rowSortDir);
            });
            colSortToggle.querySelectorAll('button').forEach(function(b) {
                b.classList.toggle('active', b.dataset.dir === state.colSortDir);
            });
        };
        var syncSelects = function() {
            fillAxisSelect(rowSel, state.colAxisId);
            fillAxisSelect(colSel, state.rowAxisId);
            rowSel.value = state.rowAxisId;
            colSel.value = state.colAxisId;
            syncSortToggles();
        };
        var withOrder = function(axis, dir) {
            if (dir !== 'desc') {
                return axis;
            }
            var copy = {};
            Object.keys(axis).forEach(function(k) { copy[k] = axis[k]; });
            copy.allValues = axis.allValues.slice().reverse();
            return copy;
        };
        var clearSelection = function() {
            state.selectedCell = null;
            sidebarView = {mode: 'empty'};
        };
        var getFilteredSubjects = function() {
            var axisIds = Object.keys(state.filters);
            if (axisIds.length === 0) {
                return subjects;
            }
            return subjects.filter(function(s) {
                return axisIds.every(function(axisId) {
                    var axis = AXES[axisId];
                    var activeVals = state.filters[axisId];
                    return axis.getValues(s).some(function(v) { return activeVals[v]; });
                });
            });
        };
        var buildMatrix = function(rowAxis, colAxis, subjectList) {
            var matrix = {};
            rowAxis.allValues.forEach(function(r) {
                matrix[r] = {};
                colAxis.allValues.forEach(function(c) { matrix[r][c] = []; });
            });
            subjectList.forEach(function(s) {
                var rVals = rowAxis.getValues(s);
                var cVals = colAxis.getValues(s);
                rVals.forEach(function(r) {
                    cVals.forEach(function(c) {
                        if (matrix[r] && matrix[r][c]) {
                            matrix[r][c].push(s);
                        }
                    });
                });
            });
            return matrix;
        };
        var rowTotal = function(matrix, rowVal, colAxis) {
            var cells = colAxis.allValues.map(function(c) { return matrix[rowVal][c]; });
            if (state.mode === 'total') {
                return cells.reduce(function(sum, arr) { return sum + arr.length; }, 0);
            }
            var uniq = {};
            cells.forEach(function(arr) { arr.forEach(function(s) { uniq[s.id] = true; }); });
            return Object.keys(uniq).length;
        };
        var colTotal = function(matrix, colVal, rowAxis) {
            var cells = rowAxis.allValues.map(function(r) { return matrix[r][colVal]; });
            if (state.mode === 'total') {
                return cells.reduce(function(sum, arr) { return sum + arr.length; }, 0);
            }
            var uniq = {};
            cells.forEach(function(arr) { arr.forEach(function(s) { uniq[s.id] = true; }); });
            return Object.keys(uniq).length;
        };
        var grandTotal = function(matrix, rowAxis, colAxis) {
            if (state.mode === 'total') {
                var sum = 0;
                rowAxis.allValues.forEach(function(r) {
                    colAxis.allValues.forEach(function(c) { sum += matrix[r][c].length; });
                });
                return sum;
            }
            var uniq = {};
            rowAxis.allValues.forEach(function(r) {
                colAxis.allValues.forEach(function(c) {
                    matrix[r][c].forEach(function(s) { uniq[s.id] = true; });
                });
            });
            return Object.keys(uniq).length;
        };
        var groupSpans = function(axis) {
            if (!axis.parentOf) {
                return null;
            }
            var groups = [];
            var last = null;
            axis.allValues.forEach(function(v) {
                var p = axis.parentOf(v);
                if (last && last.key === p) {
                    last.span++;
                } else {
                    last = {key: p, span: 1};
                    groups.push(last);
                }
            });
            return groups.map(function(g) {
                var parentAxis = AXES[axis.parentLabel];
                return {label: g.key, span: g.span, color: parentAxis ? parentAxis.colorOf(g.key) : '#999'};
            });
        };
        var globalMax = function(matrix, rowAxis, colAxis) {
            var m = 1;
            rowAxis.allValues.forEach(function(r) {
                colAxis.allValues.forEach(function(c) { m = Math.max(m, matrix[r][c].length); });
            });
            return m;
        };
        var labelOfValue = function(axis, v) {
            return axis.labelOf ? axis.labelOf(v) : v;
        };

        // ---------- filter blocks ----------
        var refreshAddFilterOptions = function() {
            addFilterAxisSel.innerHTML = '';
            var remaining = AXIS_ORDER.filter(function(id) { return !state.filters[id]; });
            if (remaining.length === 0) {
                var opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '—';
                addFilterAxisSel.appendChild(opt);
                addFilterAxisSel.disabled = true;
                return;
            }
            addFilterAxisSel.disabled = false;
            remaining.forEach(function(id) {
                var o = document.createElement('option');
                o.value = id;
                o.textContent = AXES[id].label;
                addFilterAxisSel.appendChild(o);
            });
        };
        var renderFilterBlocks = function() {
            refreshAddFilterOptions();
            filterBlocksEl.innerHTML = '';
            Object.keys(state.filters).forEach(function(axisId) {
                var axis = AXES[axisId];
                var activeVals = state.filters[axisId];
                var block = document.createElement('div');
                block.className = 'cmviz-filterblock';

                var label = document.createElement('div');
                label.className = 'cmviz-blocklabel';
                label.textContent = axis.label;
                block.appendChild(label);

                var valueWrap = document.createElement('div');
                valueWrap.className = 'cmviz-filtervalues';
                axis.allValues.forEach(function(v) {
                    var pill = document.createElement('button');
                    pill.type = 'button';
                    pill.className = 'cmviz-pill' + (activeVals[v] ? ' on' : '');
                    pill.textContent = labelOfValue(axis, v);
                    pill.addEventListener('click', function() {
                        if (activeVals[v]) {
                            delete activeVals[v];
                        } else {
                            activeVals[v] = true;
                        }
                        render();
                    });
                    valueWrap.appendChild(pill);
                });
                block.appendChild(valueWrap);

                var btnWrap = document.createElement('div');
                btnWrap.style.display = 'flex';
                btnWrap.style.gap = '6px';
                var allBtn = document.createElement('button');
                allBtn.className = 'cmviz-minibtn';
                allBtn.textContent = strings.viz_selectall;
                allBtn.addEventListener('click', function() {
                    var fresh = {};
                    axis.allValues.forEach(function(v) { fresh[v] = true; });
                    state.filters[axisId] = fresh;
                    render();
                });
                var noneBtn = document.createElement('button');
                noneBtn.className = 'cmviz-minibtn';
                noneBtn.textContent = strings.viz_selectnone;
                noneBtn.addEventListener('click', function() {
                    state.filters[axisId] = {};
                    render();
                });
                var rmBtn = document.createElement('button');
                rmBtn.className = 'cmviz-removefilter';
                rmBtn.textContent = strings.viz_removefilter;
                rmBtn.addEventListener('click', function() {
                    delete state.filters[axisId];
                    render();
                });
                btnWrap.appendChild(allBtn);
                btnWrap.appendChild(noneBtn);
                btnWrap.appendChild(rmBtn);
                block.appendChild(btnWrap);

                filterBlocksEl.appendChild(block);
            });
        };

        // ---------- sidebar ----------
        var toggleCompare = function(id) {
            if (state.compareSet[id]) {
                delete state.compareSet[id];
            } else {
                state.compareSet[id] = true;
            }
            render();
        };
        var renderCompareBox = function(el) {
            var box = document.createElement('div');
            box.className = 'cmviz-comparebox';
            var count = Object.keys(state.compareSet).length;
            var h = document.createElement('h5');
            h.textContent = strings.viz_comparelist.replace('__COUNT__', String(count));
            box.appendChild(h);

            var search = document.createElement('input');
            search.className = 'cmviz-comparesearch';
            search.placeholder = strings.viz_comparesearchplaceholder;
            box.appendChild(search);
            var results = document.createElement('div');
            box.appendChild(results);
            search.addEventListener('input', function() {
                results.innerHTML = '';
                var q = search.value.trim();
                if (!q) {
                    return;
                }
                subjects.filter(function(s) { return s.name.indexOf(q) !== -1; }).slice(0, 6).forEach(function(s) {
                    var row = document.createElement('div');
                    row.className = 'cmviz-subjrow';
                    row.textContent = s.name + (state.compareSet[s.id] ? ' ✓' : '');
                    row.addEventListener('click', function() {
                        state.compareSet[s.id] = true;
                        search.value = '';
                        results.innerHTML = '';
                        render();
                    });
                    results.appendChild(row);
                });
            });

            var list = document.createElement('div');
            list.className = 'cmviz-comparelist';
            Object.keys(state.compareSet).forEach(function(id) {
                var s = subjects.filter(function(x) { return x.id === id; })[0];
                if (!s) {
                    return;
                }
                var row = document.createElement('div');
                row.className = 'cmviz-subjrow';
                var name = document.createElement('span');
                name.className = 'cmviz-name';
                name.textContent = s.name;
                var rm = document.createElement('span');
                rm.className = 'cmviz-rm';
                rm.textContent = strings.viz_remove;
                rm.addEventListener('click', function() {
                    delete state.compareSet[id];
                    render();
                });
                row.appendChild(name);
                row.appendChild(rm);
                list.appendChild(row);
            });
            box.appendChild(list);
            el.appendChild(box);
        };
        var renderDetail = function(el, subjectId) {
            var s = subjects.filter(function(x) { return x.id === subjectId; })[0];
            if (!s) {
                return;
            }
            var back = document.createElement('button');
            back.className = 'cmviz-backbtn';
            back.textContent = strings.viz_backtolist;
            back.addEventListener('click', function() { sidebarView = {mode: 'empty'}; render(); });
            el.appendChild(back);

            var h = document.createElement('div');
            h.className = 'cmviz-detailname';
            h.textContent = s.name;
            el.appendChild(h);

            if (s.courseid) {
                var link = document.createElement('a');
                link.className = 'cmviz-courselink';
                link.href = M.cfg.wwwroot + '/course/view.php?id=' + s.courseid;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = strings.viz_opencourse;
                el.appendChild(link);
            }

            AXIS_ORDER.forEach(function(axisId) {
                var axis = AXES[axisId];
                var values = axis.getValues(s);
                var block = document.createElement('div');
                block.className = 'cmviz-axisblock';
                var t = document.createElement('div');
                t.className = 'cmviz-axistitle';
                t.textContent = axis.label;
                block.appendChild(t);
                var row = document.createElement('div');
                row.className = 'cmviz-tagrow';
                if (values.length === 0) {
                    var empty = document.createElement('span');
                    empty.className = 'cmviz-tag plain';
                    empty.textContent = strings.viz_novaluelabel;
                    row.appendChild(empty);
                } else {
                    values.forEach(function(v) {
                        var tag = document.createElement('span');
                        tag.className = 'cmviz-tag' + (axis.colorOf ? '' : ' plain');
                        if (axis.colorOf) {
                            tag.style.background = axis.colorOf(v);
                        }
                        tag.textContent = labelOfValue(axis, v);
                        row.appendChild(tag);
                    });
                }
                block.appendChild(row);
                el.appendChild(block);
            });

            var cb = document.createElement('label');
            cb.style.fontSize = '12px';
            cb.style.display = 'flex';
            cb.style.gap = '6px';
            cb.style.marginTop = '10px';
            cb.style.cursor = 'pointer';
            var cbInput = document.createElement('input');
            cbInput.type = 'checkbox';
            cbInput.checked = !!state.compareSet[s.id];
            cbInput.addEventListener('change', function() { toggleCompare(s.id); });
            cb.appendChild(cbInput);
            cb.appendChild(document.createTextNode(strings.viz_addtocompare));
            el.appendChild(cb);
        };
        var renderCellList = function(el, matrix, rowAxis, colAxis) {
            var r = state.selectedCell.r;
            var c = state.selectedCell.c;
            var list = matrix[r][c];
            var head = document.createElement('div');
            head.className = 'cmviz-cellhead';
            head.innerHTML = '<b>' + rowAxis.label + ': ' + labelOfValue(rowAxis, r) + '</b> × <b>'
                + colAxis.label + ': ' + labelOfValue(colAxis, c) + '</b> (' + list.length + ')';
            el.appendChild(head);
            if (list.length === 0) {
                var p = document.createElement('p');
                p.className = 'cmviz-empty';
                p.textContent = strings.viz_nomatch;
                el.appendChild(p);
                return;
            }
            list.forEach(function(s) {
                var row = document.createElement('div');
                row.className = 'cmviz-subjrow';
                var cbEl = document.createElement('input');
                cbEl.type = 'checkbox';
                cbEl.checked = !!state.compareSet[s.id];
                cbEl.addEventListener('click', function(e) { e.stopPropagation(); toggleCompare(s.id); });
                var name = document.createElement('span');
                name.className = 'cmviz-name';
                name.textContent = s.name;
                row.appendChild(cbEl);
                row.appendChild(name);
                row.addEventListener('click', function() { sidebarView = {mode: 'detail', subjectId: s.id}; render(); });
                el.appendChild(row);
            });
        };
        var renderSidebar = function(matrix, rowAxis, colAxis) {
            sidebarEl.innerHTML = '';
            var h = document.createElement('h4');
            h.textContent = ''; // Kept minimal - the cellHead/detail name already orient the user.
            if (sidebarView.mode === 'detail') {
                renderDetail(sidebarEl, sidebarView.subjectId);
            } else if (state.selectedCell) {
                renderCellList(sidebarEl, matrix, rowAxis, colAxis);
            } else {
                var p = document.createElement('p');
                p.className = 'cmviz-empty';
                p.textContent = strings.viz_clickforlist;
                sidebarEl.appendChild(p);
            }
            renderCompareBox(sidebarEl);
        };

        // ---------- main render ----------
        var render = function() {
            var rowAxis = withOrder(AXES[state.rowAxisId], state.rowSortDir);
            var colAxis = withOrder(AXES[state.colAxisId], state.colSortDir);
            var filteredSubjects = getFilteredSubjects();
            var matrix = buildMatrix(rowAxis, colAxis, filteredSubjects);
            renderFilterBlocks();
            statLineEl.textContent = strings.viz_statline
                .replace('__SHOWN__', String(filteredSubjects.length))
                .replace('__TOTAL__', String(subjects.length));

            tableEl.innerHTML = '';
            var colBand = groupSpans(colAxis);
            var rowBand = groupSpans(rowAxis);

            var thead = document.createElement('thead');
            if (colBand) {
                var bandTr = document.createElement('tr');
                var corner = document.createElement('th');
                corner.className = 'cmviz-corner';
                corner.colSpan = rowBand ? 2 : 1;
                bandTr.appendChild(corner);
                colBand.forEach(function(g) {
                    var th = document.createElement('th');
                    th.className = 'cmviz-band';
                    th.colSpan = g.span;
                    th.style.background = g.color;
                    th.textContent = g.label;
                    bandTr.appendChild(th);
                });
                var totalTh = document.createElement('th');
                totalTh.className = 'cmviz-total';
                totalTh.textContent = strings.viz_total;
                bandTr.appendChild(totalTh);
                thead.appendChild(bandTr);
            }
            var labelTr = document.createElement('tr');
            var corner2 = document.createElement('th');
            corner2.className = 'cmviz-corner2' + (colBand ? '' : ' cmviz-no-band');
            corner2.colSpan = rowBand ? 2 : 1;
            corner2.textContent = rowAxis.label + ' ＼ ' + colAxis.label;
            labelTr.appendChild(corner2);
            colAxis.allValues.forEach(function(c) {
                var th = document.createElement('th');
                th.className = 'cmviz-collabel' + (colBand ? '' : ' cmviz-no-band');
                th.textContent = labelOfValue(colAxis, c);
                labelTr.appendChild(th);
            });
            var totalTh2 = document.createElement('th');
            totalTh2.className = 'cmviz-total';
            totalTh2.textContent = colBand ? '' : strings.viz_total;
            labelTr.appendChild(totalTh2);
            thead.appendChild(labelTr);
            tableEl.appendChild(thead);

            var tbody = document.createElement('tbody');
            var rowBandPtr = 0;
            var rowBandRemain = rowBand ? rowBand[0].span : 0;
            rowAxis.allValues.forEach(function(r) {
                var tr = document.createElement('tr');
                if (rowBand) {
                    if (rowBandRemain === (rowBand[rowBandPtr] ? rowBand[rowBandPtr].span : 0) && rowBandRemain > 0) {
                        var th = document.createElement('th');
                        th.className = 'cmviz-rowband';
                        th.rowSpan = rowBand[rowBandPtr].span;
                        th.style.background = rowBand[rowBandPtr].color;
                        th.textContent = rowBand[rowBandPtr].label;
                        tr.appendChild(th);
                    }
                    rowBandRemain--;
                    if (rowBandRemain === 0) {
                        rowBandPtr++;
                        rowBandRemain = rowBand[rowBandPtr] ? rowBand[rowBandPtr].span : 0;
                    }
                }
                var rowLabelTh = document.createElement('th');
                rowLabelTh.className = 'cmviz-rowlabel' + (rowBand ? '' : ' cmviz-no-band');
                rowLabelTh.textContent = labelOfValue(rowAxis, r);
                tr.appendChild(rowLabelTh);

                colAxis.allValues.forEach(function(c) {
                    var list = matrix[r][c];
                    var td = document.createElement('td');
                    td.className = 'cmviz-cell' + (list.length === 0 ? ' zero' : '');
                    td.title = list.length ? (r + ' × ' + c + ': ' + list.length) : '';

                    if (state.cellDisplay === 'segments') {
                        td.classList.add('segmented');
                        if (list.length) {
                            var wrap = document.createElement('div');
                            wrap.className = 'cmviz-segwrap';
                            var baseColor = colAxis.colorOf ? colAxis.colorOf(c)
                                : (rowAxis.colorOf ? rowAxis.colorOf(r) : 'var(--cmviz-accent)');
                            list.forEach(function(s, i) {
                                var seg = document.createElement('div');
                                seg.className = 'cmviz-seg';
                                seg.style.background = baseColor;
                                seg.style.opacity = (i % 2 === 0) ? '1' : '0.7';
                                seg.title = s.name;
                                seg.addEventListener('click', function(ev) {
                                    ev.stopPropagation();
                                    state.selectedCell = {r: r, c: c};
                                    sidebarView = {mode: 'detail', subjectId: s.id};
                                    render();
                                });
                                wrap.appendChild(seg);
                            });
                            td.appendChild(wrap);
                        }
                    } else {
                        var maxCount = globalMax(matrix, rowAxis, colAxis);
                        var alpha = list.length === 0 ? 0 : 0.15 + 0.65 * (list.length / Math.max(1, maxCount));
                        td.style.background = list.length
                            ? 'color-mix(in srgb, var(--cmviz-accent) ' + Math.round(alpha * 100) + '%, white)'
                            : 'transparent';
                        td.innerHTML = '<span class="cmviz-count">' + (list.length || '') + '</span>';
                    }

                    if (state.selectedCell && state.selectedCell.r === r && state.selectedCell.c === c) {
                        td.classList.add('selected');
                    }
                    if (Object.keys(state.compareSet).length > 0) {
                        var hasCompare = list.some(function(s) { return state.compareSet[s.id]; });
                        if (!hasCompare) {
                            td.classList.add('dimmed');
                        }
                    }
                    td.addEventListener('click', function() {
                        state.selectedCell = {r: r, c: c};
                        sidebarView = {mode: 'empty'};
                        render();
                    });
                    tr.appendChild(td);
                });

                var totalTd = document.createElement('td');
                totalTd.className = 'cmviz-total';
                totalTd.textContent = rowTotal(matrix, r, colAxis);
                tr.appendChild(totalTd);
                tbody.appendChild(tr);
            });

            var totalTr = document.createElement('tr');
            var totalLabelTh = document.createElement('th');
            totalLabelTh.className = 'cmviz-total';
            totalLabelTh.colSpan = rowBand ? 2 : 1;
            totalLabelTh.textContent = strings.viz_total;
            totalTr.appendChild(totalLabelTh);
            colAxis.allValues.forEach(function(c) {
                var td = document.createElement('td');
                td.className = 'cmviz-total';
                td.textContent = colTotal(matrix, c, rowAxis);
                totalTr.appendChild(td);
            });
            var grandTd = document.createElement('td');
            grandTd.className = 'cmviz-total';
            grandTd.textContent = grandTotal(matrix, rowAxis, colAxis);
            totalTr.appendChild(grandTd);
            tbody.appendChild(totalTr);
            tableEl.appendChild(tbody);

            renderSidebar(matrix, rowAxis, colAxis);
        };

        // ---------- event wiring ----------
        rowSel.addEventListener('change', function() {
            state.rowAxisId = rowSel.value;
            clearSelection();
            syncSelects();
            render();
        });
        colSel.addEventListener('change', function() {
            state.colAxisId = colSel.value;
            clearSelection();
            syncSelects();
            render();
        });
        swapBtn.addEventListener('click', function() {
            var tmpAxis = state.rowAxisId;
            state.rowAxisId = state.colAxisId;
            state.colAxisId = tmpAxis;
            var tmpDir = state.rowSortDir;
            state.rowSortDir = state.colSortDir;
            state.colSortDir = tmpDir;
            clearSelection();
            syncSelects();
            render();
        });
        rowSortToggle.addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn) {
                return;
            }
            state.rowSortDir = btn.dataset.dir;
            syncSortToggles();
            render();
        });
        colSortToggle.addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn) {
                return;
            }
            state.colSortDir = btn.dataset.dir;
            syncSortToggles();
            render();
        });
        modeToggle.addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn) {
                return;
            }
            state.mode = btn.dataset.mode;
            modeToggle.querySelectorAll('button').forEach(function(b) { b.classList.toggle('active', b === btn); });
            render();
        });
        cellDisplayToggle.addEventListener('click', function(e) {
            var btn = e.target.closest('button');
            if (!btn) {
                return;
            }
            state.cellDisplay = btn.dataset.disp;
            cellDisplayToggle.querySelectorAll('button').forEach(function(b) { b.classList.toggle('active', b === btn); });
            render();
        });
        resetBtn.addEventListener('click', function() {
            clearSelection();
            state.compareSet = {};
            render();
        });
        addFilterBtn.addEventListener('click', function() {
            var axisId = addFilterAxisSel.value;
            if (!axisId || state.filters[axisId]) {
                return;
            }
            var fresh = {};
            AXES[axisId].allValues.forEach(function(v) { fresh[v] = true; });
            state.filters[axisId] = fresh;
            clearSelection();
            render();
        });

        // ---------- init ----------
        syncSelects();
        render();
    };

    /**
     * Initialise the full inline visualization into the given container.
     *
     * @param {string} regionId DOM id of the container to render into
     * @return {Promise}
     */
    var init = function(regionId) {
        var pending = new Pending('block_curriculummap/curriculummap:init');
        var container = document.getElementById(regionId);
        if (!container) {
            pending.resolve();
            return null;
        }

        var stringrequests = [
            {key: 'viz_categoryaxis', component: 'block_curriculummap'},
            {key: 'viz_majorsuffix', component: 'block_curriculummap'},
            {key: 'viz_total', component: 'block_curriculummap'},
            {key: 'viz_selectall', component: 'block_curriculummap'},
            {key: 'viz_selectnone', component: 'block_curriculummap'},
            {key: 'viz_removefilter', component: 'block_curriculummap'},
            {key: 'viz_clickforlist', component: 'block_curriculummap'},
            {key: 'viz_backtolist', component: 'block_curriculummap'},
            {key: 'viz_addtocompare', component: 'block_curriculummap'},
            {key: 'viz_comparesearchplaceholder', component: 'block_curriculummap'},
            {key: 'viz_remove', component: 'block_curriculummap'},
            {key: 'viz_opencourse', component: 'block_curriculummap'},
            {key: 'viz_novaluelabel', component: 'block_curriculummap'},
            {key: 'viz_nomatch', component: 'block_curriculummap'},
            {key: 'viz_statline', component: 'block_curriculummap', param: {shown: '__SHOWN__', total: '__TOTAL__'}},
            {key: 'viz_comparelist', component: 'block_curriculummap', param: '__COUNT__'}
        ];
        var stringkeys = stringrequests.map(function(r) { return r.key; });

        return Str.get_strings(stringrequests).then(function(results) {
            var strings = {};
            stringkeys.forEach(function(k, i) { strings[k] = results[i]; });
            return fetchData().then(function(data) {
                createApp(container, data.axes, data.subjects, strings);
                return null;
            });
        }).catch(function(err) {
            Notification.exception(err);
        }).then(function() {
            pending.resolve();
            return null;
        });
    };

    /**
     * Attach click handlers so 'modal' displaymode links open the full view
     * in a popup (core/modal) instead of navigating to view.php.
     *
     * @return {void}
     */
    var initModalTrigger = function() {
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest(SELECTORS.MODAL_TRIGGER);
            if (!trigger) {
                return;
            }
            e.preventDefault();

            var pending = new Pending('block_curriculummap/curriculummap:modal');
            require(['core/modal', 'core/modal_events', 'core/templates'], function(Modal, ModalEvents, Templates) {
                var modalregion = 'curriculummap-modal-body-' + Math.random().toString(36).slice(2);
                Templates.render('block_curriculummap/full', {region: modalregion}).then(function(html) {
                    return Modal.create({
                        title: trigger.textContent.trim(),
                        body: html,
                        large: true,
                        show: true,
                        removeOnClose: true
                    }).then(function(modal) {
                        modal.getRoot().on(ModalEvents.hidden, function() { modal.destroy(); });
                        modal.getRoot().on(ModalEvents.shown, function() { init(modalregion); });
                        return null;
                    });
                }).catch(function(err) {
                    Notification.exception(err);
                    window.location.href = trigger.href;
                }).then(function() {
                    pending.resolve();
                    return null;
                });
            });
        });
    };

    return {
        init: init,
        initModalTrigger: initModalTrigger
    };
});
