{{--
    Persistent in-module wayfinding for the PC module, shown at the top of
    every PC page (not just the ones reached via a tab). Mirrors the sidebar
    submenu (layouts/admin.blade.php) so the same destinations are reachable
    whether the user arrived from a cold sidebar click or is already a few
    pages deep inside the module.

    Only 3 of the sidebar's 4 destinations are "places" (Dashboard /
    Contract List / Data Management) — New Contract is an action, not
    somewhere you stay, so it sits apart as a button rather than a 4th tab:
    mixing a verb into a set of noun destinations is the kind of thing that
    reads fine individually but makes the whole bar harder to scan at a
    glance ("wait, which of these is a page and which is a command?").

    $pcSection: 'dashboard' | 'new-contract' | 'contracts' | 'data'
--}}
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <ul class="nav pc-ledger-tabs mb-0">
        <li class="nav-item">
            <a href="{{ route('admin.pc.index') }}" class="nav-link {{ ($pcSection ?? '') === 'dashboard' ? 'active' : '' }}">
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.pc.contracts.index') }}" class="nav-link {{ ($pcSection ?? '') === 'contracts' ? 'active' : '' }}">
                Contract List
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.pc.data.index') }}" class="nav-link {{ ($pcSection ?? '') === 'data' ? 'active' : '' }}">
                Data Management
            </a>
        </li>
    </ul>
    <a href="{{ route('admin.pc.contracts.create') }}" class="btn btn-sm pc-btn-primary">
        <i class="fas fa-plus me-1"></i>New Contract
    </a>
</div>
