{{--
    Manual admin override for the job_trans_raf RPA run's status — see
    SubconAdminController::setRafStatus()'s docblock. Shown next to the RAF
    badge whenever it isn't already 'completed'. Two tiny inline forms rather
    than a single select+submit: this sits inside a dense list row, so a
    one-click action beats an extra confirm step for something an admin can
    already see printed error detail for elsewhere (the queue row itself).
--}}
<div class="d-flex gap-1 mt-1">
    <form method="POST" action="{{ route('subcon.admin.raf-status.set', ['projectId' => $projectId]) }}"
          onsubmit="return confirm('Mark RAF as Completed for this project? Use this only after confirming the D365 job transaction was actually updated correctly.');">
        @csrf
        <input type="hidden" name="status" value="completed">
        <button type="submit" class="btn btn-outline-success btn-sm py-0 px-1" style="font-size: .7rem;" title="Mark RAF Completed">
            <i class="fas fa-check"></i>
        </button>
    </form>
    <form method="POST" action="{{ route('subcon.admin.raf-status.set', ['projectId' => $projectId]) }}"
          onsubmit="return confirm('Mark RAF as Failed for this project? This just updates the status label — it does not stop or retry the automaton.');">
        @csrf
        <input type="hidden" name="status" value="failed">
        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size: .7rem;" title="Mark RAF Failed">
            <i class="fas fa-xmark"></i>
        </button>
    </form>
</div>
