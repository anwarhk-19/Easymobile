import { Link, Navigate } from 'react-router-dom';
import { Alert, Async, Button, Empty, PageHeader, Section, Stars, Stat, StatusBadge } from '../../components/ui';
import { useApi } from '../../lib/hooks';
import { ago, money } from '../../lib/format';

export default function Dashboard() {
    const state = useApi('/merchant/dashboard');
    if (state.error?.data?.code === 'shop_required') return <Navigate to="/merchant/shop" replace />;
    return (
        <Async state={state}>
            {({ shop, stats: s, action_required: a, recent_reviews }) => (
                <div className="space-y-5">
                    <PageHeader title={shop.name} subtitle="Shop dashboard" actions={<><Button as={Link} to="/merchant/products/new">Add product</Button>{shop.status === 'approved' && <Button as={Link} to={`/shops/${shop.slug}`} variant="secondary">View public page</Button>}</>} />
                    {shop.status === 'pending' && <Alert tone="amber" icon="clock" title="Your shop is awaiting verification">You can add products now. They become visible to customers once our team approves your shop.</Alert>}
                    {shop.status === 'rejected' && <Alert tone="red" title="Verification was not approved">{shop.rejection_reason} — <Link className="font-semibold underline" to="/merchant/shop">update your details</Link> to resubmit.</Alert>}
                    {shop.status === 'suspended' && <Alert tone="red" title="Your shop is suspended">{shop.rejection_reason}. Contact support.</Alert>}
                    {s.stale_stock_products > 0 && <Alert tone="amber" icon="clock" title={`${s.stale_stock_products} product(s) need a stock check`}>Customers see how recently stock was confirmed. <Link className="font-semibold underline" to="/merchant/products?status=stale">Confirm stock</Link></Alert>}

                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Stat label="Total products" value={s.total_products} icon="box" to="/merchant/products" />
                        <Stat label="Active products" value={s.active_products} icon="check" tone="green" to="/merchant/products?status=active" />
                        <Stat label="Out of stock" value={s.out_of_stock_products} icon="alert" tone="red" to="/merchant/products?status=out_of_stock" />
                        <Stat label="New enquiries" value={s.new_enquiries} icon="message" tone="amber" to="/merchant/enquiries" />
                        <Stat label="Pending orders" value={s.pending_orders} icon="clock" tone="amber" to="/merchant/orders?status=pending" />
                        <Stat label="Confirmed orders" value={s.confirmed_orders} icon="bag" tone="blue" to="/merchant/orders?status=active" />
                        <Stat label="Completed orders" value={s.completed_orders} icon="check" tone="green" to="/merchant/orders?status=completed" />
                        <Stat label="Cancelled orders" value={s.cancelled_orders} icon="x" tone="slate" to="/merchant/orders?status=closed" />
                        <Stat label="Sales this month" value={money(s.monthly_sales)} icon="rupee" />
                        <Stat label="Customer rating" value={s.rating_count ? `${Number(s.rating_avg).toFixed(1)} ★` : '—'} hint={`${s.rating_count} reviews`} icon="star" tone="amber" />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Section title="Orders needing action" action={<Link to="/merchant/orders?status=action" className="text-sm font-semibold text-brand-700">All</Link>}>
                            {a.orders.length === 0 ? <p className="text-sm text-slate-500">Nothing waiting on you. 🎉</p> : (
                                <ul className="divide-y divide-slate-100">{a.orders.map((o) => (
                                    <li key={o.id}><Link to={`/merchant/orders/${o.id}`} className="flex items-center justify-between gap-3 py-2.5 text-sm hover:text-brand-700"><span><b>{o.reference}</b> · {o.customer?.name}<span className="block text-xs text-slate-400">{ago(o.created_at)}</span></span><StatusBadge status={o.status} /></Link></li>
                                ))}</ul>
                            )}
                        </Section>
                        <Section title="New enquiries" action={<Link to="/merchant/enquiries" className="text-sm font-semibold text-brand-700">All</Link>}>
                            {a.enquiries.length === 0 ? <p className="text-sm text-slate-500">No new enquiries.</p> : (
                                <ul className="divide-y divide-slate-100">{a.enquiries.map((e) => (
                                    <li key={e.id} className="py-2.5 text-sm"><p><b>{e.customer?.name}</b>{e.product && <> about {e.product.name}</>}</p><p className="truncate text-slate-600">{e.message}</p></li>
                                ))}</ul>
                            )}
                        </Section>
                    </div>
                    <Section title="Recent reviews">
                        {recent_reviews.length === 0 ? <Empty icon="star" title="No reviews yet">Customers can review after a completed order.</Empty> : (
                            <ul className="divide-y divide-slate-100">{recent_reviews.map((r) => <li key={r.id} className="py-2.5 text-sm"><Stars value={r.rating} /> <span className="text-slate-700">{r.comment}</span> <span className="text-xs text-slate-400">— {r.customer?.name}</span></li>)}</ul>
                        )}
                    </Section>
                </div>
            )}
        </Async>
    );
}
