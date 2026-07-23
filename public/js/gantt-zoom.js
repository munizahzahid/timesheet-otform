(function() {
    'use strict';

    const GANTT_ZOOM = {
        day:   { pixelsPerDay: 30,  rows: ['year', 'month', 'day'] },
        week:  { pixelsPerDay: 8,   rows: ['year', 'month', 'week'] },
        month: { pixelsPerDay: 2.5, rows: ['year', 'month'] },
        year:  { pixelsPerDay: 0.5, rows: ['year'] }
    };

    function daysBetween(startStr, endStr) {
        const start = new Date(startStr + 'T00:00:00');
        const end = new Date(endStr + 'T00:00:00');
        return Math.round((end - start) / (1000 * 60 * 60 * 24));
    }

    function getHeaderRows(startStr, endStr, zoomLevel) {
        const start = new Date(startStr + 'T00:00:00');
        const end = new Date(endStr + 'T00:00:00');
        const totalDays = daysBetween(startStr, endStr);
        const rows = [];

        function addYearBlocks() {
            const blocks = [];
            let currentYear = start.getFullYear();
            const endYear = end.getFullYear();
            while (currentYear <= endYear) {
                const yearStart = new Date(currentYear, 0, 1);
                const yearEnd = new Date(currentYear, 11, 31);
                const blockStart = new Date(Math.max(yearStart, start));
                const blockEnd = new Date(Math.min(yearEnd, end));
                blocks.push({
                    startOffset: daysBetween(startStr, blockStart.toISOString().split('T')[0]),
                    endOffset: daysBetween(startStr, blockEnd.toISOString().split('T')[0]),
                    label: currentYear
                });
                currentYear++;
            }
            rows.push({ type: 'year', height: 22, blocks: blocks });
        }

        function addMonthBlocks() {
            const blocks = [];
            let current = new Date(start);
            while (current <= end) {
                const year = current.getFullYear();
                const month = current.getMonth();
                const monthStart = new Date(year, month, 1);
                const monthEnd = new Date(year, month + 1, 0);
                const blockStart = new Date(Math.max(monthStart, start));
                const blockEnd = new Date(Math.min(monthEnd, end));
                blocks.push({
                    startOffset: daysBetween(startStr, blockStart.toISOString().split('T')[0]),
                    endOffset: daysBetween(startStr, blockEnd.toISOString().split('T')[0]),
                    label: current.toLocaleString('en-US', { month: 'short' })
                });
                current.setMonth(current.getMonth() + 1);
            }
            rows.push({ type: 'month', height: 22, blocks: blocks });
        }

        function addWeekBlocks() {
            const blocks = [];
            let weekIndex = 1;
            for (let i = 0; i <= totalDays; i += 7) {
                const chunkEnd = Math.min(i + 6, totalDays);
                blocks.push({
                    startOffset: i,
                    endOffset: chunkEnd,
                    label: 'W' + weekIndex
                });
                weekIndex++;
            }
            rows.push({ type: 'week', height: 26, blocks: blocks });
        }

        function addDayBlocks() {
            const blocks = [];
            for (let i = 0; i <= totalDays; i++) {
                const d = new Date(start);
                d.setDate(d.getDate() + i);
                blocks.push({
                    startOffset: i,
                    endOffset: i,
                    label: d.getDate()
                });
            }
            rows.push({ type: 'day', height: 26, blocks: blocks });
        }

        GANTT_ZOOM[zoomLevel].rows.forEach(function(rowType) {
            if (rowType === 'year') addYearBlocks();
            if (rowType === 'month') addMonthBlocks();
            if (rowType === 'week') addWeekBlocks();
            if (rowType === 'day') addDayBlocks();
        });

        return rows;
    }

    function renderHeader(zoomLevel) {
        const header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        const startStr = header.dataset.timelineStart;
        const endStr = header.dataset.timelineEnd;
        const totalDays = parseInt(header.dataset.totalDays, 10);
        const todayOffset = parseInt(header.dataset.todayOffset, 10);
        const config = GANTT_ZOOM[zoomLevel];
        const pixelsPerDay = config.pixelsPerDay;
        const totalWidth = totalDays * pixelsPerDay;

        header.style.width = totalWidth + 'px';
        header.style.minWidth = totalWidth + 'px';

        const rows = getHeaderRows(startStr, endStr, zoomLevel);
        let html = '';
        let top = 0;

        rows.forEach(function(row, index) {
            const isLast = index === rows.length - 1;
            const borderBottom = isLast ? '' : 'border-bottom: 1px solid #d1d5db;';
            html += '<div class="absolute" style="left: 0; right: 0; top: ' + top + 'px; height: ' + row.height + 'px; ' + borderBottom + '">';
            row.blocks.forEach(function(block) {
                const blockWidth = (block.endOffset - block.startOffset + 1) * pixelsPerDay;
                const bgClass = row.type === 'year' ? 'bg-gray-100' : (row.type === 'month' ? 'bg-gray-50' : '');
                const bgStyle = bgClass ? '' : 'background-color: transparent;';
                const fontWeight = row.type === 'year' || row.type === 'month' ? 'font-semibold' : '';
                const fontSize = row.type === 'day' ? 'text-[9px]' : 'text-[10px]';
                const borderRight = row.type === 'day' ? 'border-right: 1px solid #e5e7eb;' : 'border-right: 1px solid #d1d5db;';
                let extraAttrs = '';
                let extraClasses = '';
                if (row.type === 'day' && block.startOffset === todayOffset) {
                    extraAttrs = ' id="gantt-today-marker"';
                    extraClasses = ' text-red-600 font-bold';
                }
                html += '<div class="absolute h-full flex items-center justify-center ' + fontSize + ' ' + fontWeight + ' text-gray-700 ' + bgClass + extraClasses + '"' +
                        extraAttrs +
                        ' style="left: ' + (block.startOffset * pixelsPerDay) + 'px; width: ' + blockWidth + 'px; ' + borderRight + ' ' + bgStyle + '">' +
                        block.label + '</div>';
            });
            html += '</div>';
            top += row.height;
        });

        header.style.height = top + 'px';
        header.innerHTML = html;
    }

    function updateBars(zoomLevel) {
        const config = GANTT_ZOOM[zoomLevel];
        const pixelsPerDay = config.pixelsPerDay;
        document.querySelectorAll('.gantt-bar').forEach(function(bar) {
            const startOffset = parseFloat(bar.dataset.startOffset);
            const duration = parseFloat(bar.dataset.duration);
            if (isNaN(startOffset) || isNaN(duration)) return;
            bar.style.left = (startOffset * pixelsPerDay) + 'px';
            bar.style.width = Math.max(duration * pixelsPerDay, 4) + 'px';
        });
    }

    function updateGridLines(zoomLevel) {
        const config = GANTT_ZOOM[zoomLevel];
        const pixelsPerDay = config.pixelsPerDay;
        const isDayView = zoomLevel === 'day';
        document.querySelectorAll('.gantt-grid-line').forEach(function(line) {
            if (!isDayView) {
                line.style.display = 'none';
                return;
            }
            line.style.display = '';
            const offset = parseFloat(line.dataset.dayOffset);
            if (isNaN(offset)) return;
            line.style.left = (offset * pixelsPerDay) + 'px';
        });
    }

    function updateTimelineAreas(zoomLevel) {
        const config = GANTT_ZOOM[zoomLevel];
        const pixelsPerDay = config.pixelsPerDay;
        const header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        const totalDays = parseInt(header.dataset.totalDays, 10);
        const totalWidth = totalDays * pixelsPerDay;
        document.querySelectorAll('.gantt-timeline-area').forEach(function(area) {
            area.style.width = totalWidth + 'px';
            area.style.minWidth = totalWidth + 'px';
        });
    }

    function updateTodayLine(zoomLevel) {
        const config = GANTT_ZOOM[zoomLevel];
        const pixelsPerDay = config.pixelsPerDay;
        const line = document.getElementById('gantt-today-line');
        if (!line) return;
        const header = document.getElementById('gantt-timeline-header');
        if (!header) return;
        const todayOffset = parseInt(header.dataset.todayOffset, 10);
        const totalDays = parseInt(header.dataset.totalDays, 10);
        const timelineLeftOffset = parseInt(header.dataset.timelineLeftOffset, 10);
        if (todayOffset < 0 || todayOffset > totalDays) {
            line.style.display = 'none';
            return;
        }
        line.style.display = '';
        line.style.left = (timelineLeftOffset + (todayOffset * pixelsPerDay)) + 'px';
    }

    function positionTodayLine() {
        var wrapper = document.getElementById('gantt-wrapper');
        var marker = document.getElementById('gantt-today-marker');
        var line = document.getElementById('gantt-today-line');
        if (!wrapper || !marker || !line) return;
        var m = marker.getBoundingClientRect();
        var w = wrapper.getBoundingClientRect();
        line.style.left = (m.left - w.left + m.width / 2) + 'px';
    }

    function applyZoom(zoomLevel) {
        renderHeader(zoomLevel);
        updateBars(zoomLevel);
        updateGridLines(zoomLevel);
        updateTimelineAreas(zoomLevel);
        updateTodayLine(zoomLevel);
        positionTodayLine();

        document.querySelectorAll('.gantt-zoom-btn').forEach(function(btn) {
            if (btn.dataset.zoom === zoomLevel) {
                btn.classList.add('bg-indigo-50', 'border-indigo-300', 'text-indigo-700');
                btn.classList.remove('bg-white', 'border-gray-300', 'text-gray-700');
            } else {
                btn.classList.remove('bg-indigo-50', 'border-indigo-300', 'text-indigo-700');
                btn.classList.add('bg-white', 'border-gray-300', 'text-gray-700');
            }
        });
    }

    function initZoom() {
        document.querySelectorAll('.gantt-zoom-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                applyZoom(this.dataset.zoom);
            });
        });
        // Initialize in day view
        applyZoom('day');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initZoom);
    } else {
        initZoom();
    }

    window.addEventListener('resize', function() {
        positionTodayLine();
    });
})();
