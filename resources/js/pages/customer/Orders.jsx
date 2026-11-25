import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Icon from '../../components/icons';
import { Alert, Async, Button, ConfirmDialog, DL, Empty, Field, Modal, PageHeader, Pagination, Section, StarInput, StatusBadge, Tabs, Textarea, Timeline, WhatsAppButton } from '../../components/ui';
import { post } from '../../lib/api';
import { useAction, useApi } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { dateTime, label, money } from '../../lib/format';

export function Orders() {
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const state = useApi('/orders', { status, page });
    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title="My orders" back="/account" />
            <Tabs value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['', 'All'], ['open', 'Active'], ['completed', 'Completed'], ['cancelled', 'Cancelled']]} className="mb-4" />
            <Async state={state}>
                {(d) => d.data.length === 0 ? <Empty icon="bag" title="No orders yet" action={<Button as={Link} to="/search">Browse products</Button>}>Order requests you send to shops will appear here.</Empty> : (
                    <>
                        <ul className="space-y-3">{d.data.map((o) => (
                            <li key={o.id}><Link to={`/orders/${o.id}`} className="card flex items-center justify-between gap-3 p-4 transition hover:border-brand-500">
                                <div className="min-w-0"><p className="font-semibold text-slate-900">{o.shop.name}</p><p className="truncate text-sm text-slate-600">{o.items.map((i) => `${i.product_name} ×${i.quantity}`).join(', ')}</p><p className="mt-1 text-xs text-slate-400">{o.reference} · {dateTime(o.created_at)}</p></div>
                                <div className="text-right"><StatusBadge status={o.status} /><p className="mt-1 font-bold text-slate-900">{Number(o.total_amount) ? money(o.total_amount) : '—'}</p></div>
                            </Link></li>
                        ))}</ul>
                        <Pagination meta={d} onPage={setPage} />
                    </>
                )}
            </Async>
        </div>
    );
}

const STEPS = ['requested', 'quoted', 'accepted', 'confirmed', 'ready', 'handed_over', 'completed'];

const STEP_LABELS = { requested: 'Requested', quoted: 'Price sent', accepted: 'Accepted', confirmed: 'Confirmed', ready: 'Ready', handed_over: 'Delivered', completed: 'Done' };

/** Labelled order tracker: done steps filled, current step ringed. */
function Progress({ status }) {
    const idx = STEPS.indexOf(status);
    if (idx < 0) return null;
    return (
        <ol className="card flex items-start px-2 py-4" aria-label="Order progress">
            {STEPS.map((s, i) => {
                const done = i < idx || status === 'completed';
                const current = i === idx && status !== 'completed';
                return (
                    <li key={s} className="relative flex flex-1 flex-col items-center text-center" aria-current={current ? 'step' : undefined}>
                        {i > 0 && <span className={`absolute right-1/2 top-3 h-0.5 w-full ${i <= idx ? 'bg-brand-500' : 'bg-slate-200'}`} aria-hidden="true" />}
                        <span className={`relative z-10 flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold ${done ? 'bg-brand-600 text-white' : current ? 'bg-white text-brand-700 ring-2 ring-brand-600' : 'bg-slate-100 text-slate-400'}`}>
                            {done ? <Icon name="check" className="h-3.5 w-3.5" strokeWidth={3} /> : i + 1}
                        </span>
                        <span className={`mt-1.5 px-0.5 text-[10px] font-semibold leading-tight sm:text-xs ${current ? 'text-brand-700' : done ? 'text-slate-700' : 'text-slate-400'}`}>{STEP_LABELS[s]}</span>
                    </li>
                );
            })}
        </ol>
    );
}

function ReviewModal({ order, onClose, onDone }) {
    const toast = useToast();
    const [shopRating, setShopRating] = useState(5);
    const [comment, setComment] = useState('');
    const [products, setProducts] = useState({});
    const act = useAction(() => post(`/orders/${order.id}/reviews`, {
        shop_rating: shopRating, shop_comment: comment,
        products: Object.entries(products).filter(([, r]) => r).map(([product_id, rating]) => ({ product_id: Number(product_id), rating })),
    }), { onSuccess: (r) => { toast(r.message); onDone(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Modal open onClose={onClose} title="Rate your purchase" footer={<><Button variant="secondary" onClick={onClose}>Later</Button><Button busy={act.busy} onClick={() => act.run()}>Submit review</Button></>}>
            <div className="space-y-4">
                <Field label={`How was ${order.shop.name}?`}><StarInput value={shopRating} onChange={setShopRating} /></Field>
                <Field label="Your review (optional)"><Textarea value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Was the product genuine? Was the shop helpful?" /></Field>
                {order.items.filter((i) => i.product_id).map((i) => <Field key={i.id} label={`Rate ${i.product_name}`}><StarInput value={products[i.product_id] || 0} onChange={(v) => setProducts((p) => ({ ...p, [i.product_id]: v }))} /></Field>)}
            </div>
        </Modal>
    );
}

export function OrderDetail() {
    const { id } = useParams();
    const toast = useToast();
    const state = useApi(`/orders/${id}`);
    const [dialog, setDialog] = useState(null);
    const act = useAction((path, body) => post(`/orders/${id}/${path}`, body), { onSuccess: () => { setDialog(null); state.reload(); toast('Order updated'); }, onError: (e) => toast(e.message, 'error') });

    return (
        <Async state={state}>
            {({ order: o, can_review, whatsapp_link }) => (
                <div className="mx-auto max-w-3xl space-y-4">
                    <PageHeader back="/orders" title={`Order ${o.reference}`} subtitle={`${o.shop.name} · placed ${dateTime(o.created_at)}`} actions={<StatusBadge status={o.status} />} />
                    <Progress status={o.status} />

                    {o.status === 'requested' && <Alert tone="amber" icon="clock" title="Waiting for the shop">{o.shop.name} will check availability and send you the final price. This is not yet a confirmed order.</Alert>}
                    {o.status === 'quoted' && (
                        <Alert tone="blue" icon="message" title="The shop has sent a final quotation">
                            Review the confirmed details and total below, then accept to let the shop confirm your order.
                            <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => setDialog('accept')}>Accept quotation · {money(o.total_amount)}</Button><Button size="sm" variant="secondary" onClick={() => setDialog('cancel')}>Decline</Button></div>
                        </Alert>
                    )}
                    {o.status === 'handed_over' && (
                        <Alert tone="green" icon="check" title={o.fulfilment === 'delivery' ? 'Marked as delivered' : 'Marked as collected'}>
                            Please confirm you received the right product in good condition.
                            <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => setDialog('receipt')}>Yes, I received it</Button><Button as={Link} size="sm" variant="secondary" to={`/support/new?order=${o.id}`}>Report a problem</Button></div>
                        </Alert>
                    )}
                    {can_review && <Alert tone="green" icon="star" title="How was your experience?">Your review helps other customers in Bhatkal.<div className="mt-3"><Button size="sm" onClick={() => setDialog('review')}>Write a review</Button></div></Alert>}

                    <Section title="Items">
                        <ul className="divide-y divide-slate-100">{o.items.map((i) => (
                            <li key={i.id} className="flex justify-between gap-3 py-2.5 text-sm">
                                <div>{i.product ? <Link to={`/products/${i.product.slug}`} className="font-semibold text-slate-900 hover:text-brand-700">{i.product_name}</Link> : <span className="font-semibold">{i.product_name}</span>}
                                    <p className="text-slate-500">Qty {i.quantity}{i.variant && ` · ${i.variant}`}{i.colour && ` · ${i.colour}`}{i.model_note && ` · ${i.model_note}`}</p></div>
                                <span className="font-semibold">{money(i.line_total)}</span>
                            </li>
                        ))}</ul>
                        <dl className="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
                            <div className="flex justify-between"><dt className="text-slate-500">Products</dt><dd>{money(o.product_amount)}</dd></div>
                            <div className="flex justify-between"><dt className="text-slate-500">{o.fulfilment === 'delivery' ? 'Delivery fee' : 'Pickup'}</dt><dd>{o.fulfilment === 'delivery' ? money(o.delivery_fee) : 'Free'}</dd></div>
                            {Number(o.refund_amount) > 0 && <div className="flex justify-between text-emerald-700"><dt>Refunded</dt><dd>−{money(o.refund_amount)}</dd></div>}
                            <div className="flex justify-between text-base font-bold"><dt>Total</dt><dd>{money(o.total_amount)}</dd></div>
                        </dl>
                        {o.status === 'requested' && <p className="mt-2 text-xs text-slate-500">Prices shown are listing prices; the shop will confirm the final amount.</p>}
                    </Section>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Section title="Fulfilment & payment">
                            <DL items={[['Method', o.fulfilment === 'delivery' ? 'Delivery' : 'Pickup from shop'], o.fulfilment === 'delivery' && ['Address', `${o.delivery_address}${o.delivery_area ? ', ' + o.delivery_area.name : ''}`],
                                ['Payment', label(o.payment_method)], ['Payment status', <StatusBadge key="p" status={o.payment_status} />], o.merchant_note && ['Shop note', o.merchant_note], o.cancel_reason && ['Cancellation reason', o.cancel_reason]]} />
                        </Section>
                        <Section title="Shop">
                            <p className="font-semibold text-slate-900">{o.shop.name}</p><p className="text-sm text-slate-600">{o.shop.address}</p>
                            <div className="mt-3 flex flex-wrap gap-2"><WhatsAppButton href={whatsapp_link} size="sm" /><Button as="a" href={`tel:+91${o.shop.phone}`} size="sm" variant="secondary"><Icon name="phone" className="h-4 w-4" />Call</Button></div>
                        </Section>
                    </div>
                    <Section title="Timeline"><Timeline items={o.histories} /></Section>
                    {['requested', 'quoted', 'accepted'].includes(o.status) && <div className="text-center"><Button variant="ghost" onClick={() => setDialog('cancel')}>Cancel this order request</Button></div>}

                    <ConfirmDialog open={dialog === 'accept'} onClose={() => setDialog(null)} busy={act.busy} title="Accept quotation?" message={`You agree to buy at ${money(o.total_amount)}. The shop will then confirm and prepare your order.`} confirmText="Accept" onConfirm={() => act.run('accept')} />
                    <ConfirmDialog open={dialog === 'receipt'} onClose={() => setDialog(null)} busy={act.busy} title="Confirm receipt" message="Confirm only if you have received the product. This completes the order." confirmText="Confirm receipt" onConfirm={() => act.run('confirm-receipt')} />
                    <ConfirmDialog open={dialog === 'cancel'} onClose={() => setDialog(null)} busy={act.busy} title="Cancel order request" tone="danger" reason confirmText="Cancel order" onConfirm={(reason) => act.run('cancel', { reason })} />
                    {dialog === 'review' && <ReviewModal order={o} onClose={() => setDialog(null)} onDone={() => { setDialog(null); state.reload(); }} />}
                </div>
            )}
        </Async>
    );
}

export function Enquiries() {
    const [page, setPage] = useState(1);
    const state = useApi('/enquiries', { page });
    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title="My enquiries" back="/account" />
            <Async state={state}>
                {(d) => d.data.length === 0 ? <Empty icon="message" title="No enquiries yet">Ask a shop about stock or price from any product page.</Empty> : (
                    <><ul className="space-y-3">{d.data.map((e) => (
                        <li key={e.id} className="card p-4">
                            <div className="flex items-start justify-between gap-3"><div><p className="font-semibold text-slate-900">{e.shop.name}</p>{e.product && <Link to={`/products/${e.product.slug}`} className="text-sm text-brand-700 hover:underline">{e.product.name}</Link>}</div><StatusBadge status={e.status} /></div>
                            <p className="mt-2 text-sm text-slate-700">“{e.message}”</p>
                            {e.merchant_reply && <div className="mt-2 rounded-xl bg-brand-50 p-3 text-sm text-slate-800"><b>Shop reply:</b> {e.merchant_reply}</div>}
                            <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400"><span>{e.reference} · {dateTime(e.created_at)}</span>{e.order ? <Link className="font-semibold text-brand-700" to={`/orders/${e.order.id}`}>View order {e.order.reference}</Link> : e.product && <Link className="font-semibold text-brand-700" to={`/products/${e.product.slug}`}>Request order →</Link>}</div>
                        </li>
                    ))}</ul><Pagination meta={d} onPage={setPage} /></>
                )}
            </Async>
        </div>
    );
}
