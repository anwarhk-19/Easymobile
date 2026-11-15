import { Empty, Loading, ErrorState, Pagination } from './ui';

/**
 * columns: [{ key, label, render?(row), className? }]
 * state: useApi() result holding a Laravel paginator (or a plain array).
 */
export default function DataTable({ columns, state, onPage, empty = 'Nothing here yet.', emptyIcon = 'list', rowKey = 'id', onRowClick }) {
    if (state.loading && !state.data) return <Loading />;
    if (state.error) return <ErrorState error={state.error} onRetry={state.reload} />;
    const rows = Array.isArray(state.data) ? state.data : state.data?.data || [];
    if (!rows.length) return <Empty icon={emptyIcon} title={empty} />;
    return (
        <>
            <div className={`card table-wrap ${state.loading ? 'opacity-60' : ''}`}>
                <table>
                    <thead><tr>{columns.map((c) => <th key={c.key} className={c.className}>{c.label}</th>)}</tr></thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r[rowKey]} onClick={onRowClick ? () => onRowClick(r) : undefined} className={onRowClick ? 'cursor-pointer hover:bg-slate-50' : ''}>
                                {columns.map((c) => <td key={c.key} className={c.className}>{c.render ? c.render(r) : r[c.key] ?? '—'}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!Array.isArray(state.data) && <Pagination meta={state.data} onPage={onPage} />}
        </>
    );
}

/** Small toolbar for list pages: search box + filter selects. */
export function Toolbar({ children }) {
    return <div className="mb-4 flex flex-wrap items-center gap-2 [&_.input]:w-auto [&_.input]:py-2 [&_.input]:text-sm">{children}</div>;
}
