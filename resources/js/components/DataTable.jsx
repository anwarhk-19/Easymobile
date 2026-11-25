import { Empty, Loading, ErrorState, Pagination } from './ui';
import Icon from './icons';

/**
 * Responsive list: a table on tablets/desktops, stacked cards on phones (no sideways scrolling).
 *
 * columns: [{ key, label, render?(row), className?, hideOnMobile? }]
 *   On phones the first column is the card title, the last column (when keyed 'a'/'actions') is the card footer,
 *   and the rest become label/value rows. Pass `card(row)` to fully customise the phone card instead.
 * state: useApi() result holding a Laravel paginator (or a plain array).
 */
export default function DataTable({ columns, state, onPage, empty = 'Nothing here yet.', emptyIcon = 'list', rowKey = 'id', onRowClick, card }) {
    if (state.loading && !state.data) return <Loading />;
    if (state.error) return <ErrorState error={state.error} onRetry={state.reload} />;
    const rows = Array.isArray(state.data) ? state.data : state.data?.data || [];
    if (!rows.length) return <Empty icon={emptyIcon} title={empty} />;
    const cell = (c, r) => (c.render ? c.render(r) : r[c.key] ?? '—');
    const [title, ...rest] = columns;
    const actionCol = rest.length && ['a', 'actions'].includes(rest[rest.length - 1].key) ? rest[rest.length - 1] : null;
    const fields = rest.filter((c) => c !== actionCol && !c.hideOnMobile);

    return (
        <>
            {/* Phones: cards */}
            <ul className={`space-y-2.5 md:hidden ${state.loading ? 'opacity-60' : ''}`}>
                {rows.map((r) => (
                    <li key={r[rowKey]}>
                        {card ? card(r) : (
                            <div className={`card p-4 ${onRowClick ? 'cursor-pointer active:bg-slate-50' : ''}`} onClick={onRowClick ? () => onRowClick(r) : undefined}
                                role={onRowClick ? 'button' : undefined} tabIndex={onRowClick ? 0 : undefined} onKeyDown={onRowClick ? (e) => e.key === 'Enter' && onRowClick(r) : undefined}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1 text-[15px]">{cell(title, r)}</div>
                                    {onRowClick && <Icon name="chevron" className="mt-0.5 h-4 w-4 shrink-0 text-slate-300" />}
                                </div>
                                {fields.length > 0 && (
                                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        {fields.map((c) => (
                                            <div key={c.key} className="min-w-0">
                                                <dt className="text-xs text-slate-500">{c.label}</dt>
                                                <dd className="truncate text-slate-800">{cell(c, r)}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                                {actionCol && <div className="mt-3 border-t border-slate-100 pt-3" onClick={(e) => e.stopPropagation()}>{cell(actionCol, r)}</div>}
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {/* Tablets and up: table */}
            <div className={`card table-wrap hidden md:block ${state.loading ? 'opacity-60' : ''}`}>
                <table>
                    <thead><tr>{columns.map((c) => <th key={c.key} className={c.className}>{c.label}</th>)}</tr></thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r[rowKey]} onClick={onRowClick ? () => onRowClick(r) : undefined} className={onRowClick ? 'cursor-pointer hover:bg-slate-50' : ''}>
                                {columns.map((c) => <td key={c.key} className={c.className}>{cell(c, r)}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!Array.isArray(state.data) && <Pagination meta={state.data} onPage={onPage} />}
        </>
    );
}

/** Toolbar for list pages: search box + filters. Full-width search on phones. */
export function Toolbar({ children }) {
    return <div className="mb-4 flex flex-wrap items-center gap-2 [&_.input]:py-2 [&_.input]:text-sm [&_input[type=search]]:min-w-0 [&_input[type=search]]:flex-1 sm:[&_.input]:w-auto sm:[&_input[type=search]]:flex-none">{children}</div>;
}
