import { Link, Navigate } from 'react-router-dom';
import Icon from '../../components/icons';
import { Alert, Async, Button, Section, Skeleton, Stars, StatusBadge } from '../../components/ui';
import { TodoList, QuickActions, KpiStrip } from '../../components/portal';
import { useApi } from '../../lib/hooks';
import { ago, money } from '../../lib/format';

function DashboardSkeleton() {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-40 w-full" /><Skeleton className="h-24 w-full" /></div>;
}

export default function Dashboard() {
    const state = useApi('/merchant/dashboard');
    if (state.error?.data?.code === 'shop_required') return <Navigate to="/merchant/shop" replace />;
    return (
        <Async state={state} skeleton={<DashboardSkeleton />}>
            {({ shop, stats: s, action_required: a, recent_reviews }) => {
                const todo = [
                    { to: '/merchant/orders?status=action', icon: 'bag', tone: 'amber', count: s.orders_to_quote, label: 'Order requests to answer', hint: 'Confirm availability and final price' },
                    { to: '/merchant/orders?status=action', icon: 'check', tone: 'green', count: s.orders_to_confirm, label: 'Accepted quotes to confirm', hint: 'Customer agreed — confirm the order' },
                    { to: '/merchant/enquiries', icon: 'message', tone: 'blue', count: s.new_enquiries, label: 'New enquiries', hint: 'Reply to customer questions' },
                    { to: '/merchant/products?status=stale', icon: 'clock', tone: 'amber', count: s.stale_stock_products, label: 'Products need a stock check', hint: 'Customers see when stock was last confirmed' },
                    { to: '/merchant/products?status=out_of_stock', icon: 'alert', tone: 'red', count: s.out_of_stock_products, label: 'Out of stock', hint: 'Restock or mark as on order' },
                ];
                return (
                    <div className="space-y-5">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-sm text-slate-500">Welcome back</p>
                                <h1 className="truncate text-2xl font-extrabold tracking-tight text-slate-900">{shop.name}</h1>
                                {shop.rating_count > 0 && <div className="mt-1"><Stars value={shop.rating_avg} count={shop.rating_count} size="h-3.5 w-3.5" /></div>}
                            </div>
                            {shop.status === 'approved' && <Button as={Link} to={`/shops/${shop.slug}`} variant="secondary" size="sm"><Icon name="store" className="h-4 w-4" />Public page</Button>}
                        </div>

                        {shop.status === 'pending' && <Alert tone="amber" icon="clock" title="Your shop is awaiting verification">Add your products now — they go live as soon as our team approves your shop.</Alert>}
                        {shop.status === 'rejected' && <Alert tone="red" title="Verification was not approved">{shop.rejection_reason} — <Link className="font-semibold underline" to="/merchant/shop">update your details</Link> to resubmit.</Alert>}
                        {shop.status === 'suspended' && <Alert tone="red" title="Your shop is suspended">{shop.rejection_reason}. Contact support.</Alert>}

                        <TodoList items={todo} done="You're all caught up — nothing needs your attention." />

                        <QuickActions actions={[
                            { to: '/merchant/products/new', icon: 'plus', label: 'Add product', primary: true },
                            { to: '/merchant/products', icon: 'box', label: 'Update stock' },
                            { to: '/merchant/orders', icon: 'bag', label: 'Orders' },
                            { to: '/merchant/reports', icon: 'chart', label: 'Reports' },
                        ]} />

                        <KpiStrip items={[
                            ['Sales this month', money(s.monthly_sales)],
                            ['Orders in progress', s.pending_orders + s.confirmed_orders],
                            ['Completed orders', s.completed_orders],
                            ['Live products', `${s.active_products}/${s.total_products}`],
                        ]} />

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Section title="Latest orders" action={<Link to="/merchant/orders" className="text-sm font-semibold text-brand-700">All orders</Link>}>
                                {a.orders.length === 0 ? <p className="text-sm text-slate-500">No orders waiting on you.</p> : (
                                    <ul className="-my-1 divide-y divide-slate-100">{a.orders.map((o) => (
                                        <li key={o.id}><Link to={`/merchant/orders/${o.id}`} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                            <span className="min-w-0"><b className="text-slate-900">{o.customer?.name}</b><span className="block text-xs text-slate-400">{o.reference} · {ago(o.created_at)}</span></span>
                                            <StatusBadge status={o.status} />
                                        </Link></li>
                                    ))}</ul>
                                )}
                            </Section>
                            <Section title="Recent reviews">
                                {recent_reviews.length === 0 ? <p className="text-sm text-slate-500">Customers can review you after a completed order.</p> : (
                                    <ul className="-my-1 divide-y divide-slate-100">{recent_reviews.map((r) => (
                                        <li key={r.id} className="py-2.5 text-sm"><Stars value={r.rating} size="h-3.5 w-3.5" />{r.comment && <p className="mt-1 text-slate-700">{r.comment}</p>}<p className="text-xs text-slate-400">{r.customer?.name}</p></li>
                                    ))}</ul>
                                )}
                            </Section>
                        </div>
                    </div>
                );
            }}
        </Async>
    );
}
