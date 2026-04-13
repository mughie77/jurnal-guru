<?php
function get_pagination_data($conn, $table, $limit, $where = "", $count_col = "*") {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $count_query = "SELECT COUNT($count_col) as total FROM $table $where";
    $count_res = mysqli_query($conn, $count_query);
    $total_data = mysqli_fetch_assoc($count_res)['total'];
    $total_pages = ceil($total_data / $limit);

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset,
        'total_data' => $total_data,
        'total_pages' => $total_pages
    ];
}

function render_pagination($current_page, $total_pages, $query_params = []) {
    if ($total_pages <= 1) return '';

    $html = '<div class="flex items-center justify-between mt-8 px-2">';

    // Info
    $html .= '<p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Halaman ' . $current_page . ' dari ' . $total_pages . '</p>';

    $html .= '<div class="flex items-center gap-1">';

    // Build query string for existing filters
    $qs = "";
    if (!empty($query_params)) {
        unset($query_params['page']);
        if (!empty($query_params)) {
            $qs = "&" . http_build_query($query_params);
        }
    }

    // Previous
    if ($current_page > 1) {
        $html .= '<a href="?page=' . ($current_page - 1) . $qs . '" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm"><i class="fa fa-chevron-left text-xs"></i></a>';
    }

    // Pages
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active_class = ($i == $current_page)
            ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-200'
            : 'bg-white text-slate-600 border-slate-100 hover:bg-slate-50 shadow-sm';
        $html .= '<a href="?page=' . $i . $qs . '" class="w-10 h-10 flex items-center justify-center rounded-xl border font-bold text-sm transition-all ' . $active_class . '">' . $i . '</a>';
    }

    // Next
    if ($current_page < $total_pages) {
        $html .= '<a href="?page=' . ($current_page + 1) . $qs . '" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all shadow-sm"><i class="fa fa-chevron-right text-xs"></i></a>';
    }

    $html .= '</div></div>';
    return $html;
}
?>
