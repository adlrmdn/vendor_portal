{{--
    Shared JS for every Daily Production page — pushed once via
    @push('scripts') so behavior is identical everywhere. Never redefine
    these per-page; edit here.
--}}
<script>
    // Both dpAddRatioColumn and dpToggleCuttingRatios need the server to
    // re-render one style's table with a new set of ratio columns (the
    // server-rendered <colgroup>/<col> widths are what make the table
    // layout reliable — see dpToggleCuttingRatios' own comment below). A
    // full page navigation would do that too, but jumps the whole page
    // back to the top and drops any unsaved input the vendor had typed
    // into OTHER styles' rows. Instead this fetches the same URL in the
    // background and swaps in just the one style's <div id="dp-style-...">
    // — everything else on the page, including other styles' unsaved
    // inputs and the scroll position, is left untouched. The URL is kept
    // in sync via replaceState so a manual page refresh still reflects the
    // current ratio columns / collapsed state.
    function dpFetchAndSwapStyleCard(orderId, url) {
        var cardId = 'dp-style-' + orderId;
        var currentCard = document.getElementById(cardId);
        if (!currentCard) {
            window.location.href = url;
            return;
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Request failed: ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                var freshCard = new DOMParser().parseFromString(html, 'text/html').getElementById(cardId);
                if (!freshCard) {
                    throw new Error('Style card not found in response');
                }
                document.adoptNode(freshCard);
                currentCard.replaceWith(freshCard);
                window.history.replaceState(null, '', url);
            })
            .catch(function () {
                // Seamless swap failed for some reason (network hiccup,
                // unexpected response) — fall back to a normal navigation
                // so the action still completes rather than silently doing
                // nothing.
                window.location.href = url;
            });
    }

    // Adds a new ratio/marker column to one style's own table via
    // ?ratios[{orderId}][]=... — keyed per order so adding a column to one
    // style never touches another's columns. Preserves every other query
    // param (date, other styles' pending ratios/collapsed state) by
    // starting from the current URL.
    function dpAddRatioColumn(orderId, existingRatios) {
        var name = prompt('Name this new Cutting ratio/marker for this style (e.g. "Ratio C"):');
        if (!name || !name.trim()) {
            return;
        }
        var params = new URLSearchParams(window.location.search);
        var key = 'ratios[' + orderId + '][]';
        params.delete(key);
        existingRatios.forEach(function (r) { params.append(key, r); });
        params.append(key, name.trim());
        dpFetchAndSwapStyleCard(orderId, window.location.pathname + '?' + params.toString());
    }

    // Collapses/expands one style's Cutting ratio sub-columns via
    // ?collapsed[]=orderId. Rendered server-side (not toggled client-side)
    // because three different client-side approaches — zeroing <col> width
    // by hand, `visibility: collapse` on the <col>s, switching
    // table-layout to auto — each broke the layout in a new way: a
    // table-layout:fixed table's <colgroup> widths have to match what's
    // actually on the page from first paint, and resizing columns after
    // the fact via JS kept fighting that. Letting the server render the
    // table without those columns at all — exactly what a style with zero
    // ratios already renders correctly — sidesteps the whole problem.
    function dpToggleCuttingRatios(orderId) {
        var params = new URLSearchParams(window.location.search);
        var key = 'collapsed[]';
        var ids = params.getAll(key);
        var idx = ids.indexOf(orderId);
        params.delete(key);
        if (idx === -1) {
            ids.push(orderId);
        } else {
            ids.splice(idx, 1);
        }
        ids.forEach(function (id) { params.append(key, id); });
        dpFetchAndSwapStyleCard(orderId, window.location.pathname + '?' + params.toString());
    }
</script>
