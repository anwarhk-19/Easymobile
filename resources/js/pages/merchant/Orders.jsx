import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import DataTable, { Toolbar } from '../../components/DataTable';
import { ListCard } from '../../components/portal';
import { Alert, Async, Button, ConfirmDialog, DL, Field, Input, Modal, PageHeader, Section, Select, StatusBadge, Tabs, Textarea, Timeline, WhatsAppButton } from '../../components/ui';
import { post } from '../../lib/api';
import { useAction, useApi, useDebounced } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { ago, dateTime, label, money } from '../../lib/format';

/** What the shop has to do next, per order status. */
const NEXT_STEP = { requested: 'Next: confirm availability & price', accepted: 'Next: confirm the order', confirmed: 'Next: mark ready', ready: 'Next: mark delivered / collected' };

export function MerchantOrders() {
    const [params, setParams] = useSearchParams();
    const navigate = useNavigate();
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const status = params.get('status') || '';
    const state = useApi('/merchant/orders', { status, q: dq, page });
    return (
        <div>
            <PageHeader title="Orders" subtitle="Order requests are not confirmed until you quote, the customer accepts, and you confirm." />
            <Tabs className="mb-3" value={status} onChange={(s) => { setParams(s ? { status: s } : {}); setPage(1); }} tabs={[['', 'All'], ['action', 'Needs action'], ['pending', 'Pending'], ['active', 'In progress'], ['completed', 'Completed'], ['closed', 'Cancelled']]} />
            <Toolbar><Input type="search" placeholder="Search reference" value={q} onChange={(e) => setQ(e.target.value)} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No orders here" emptyIcon="bag" onRowClick={(o) => navigate(`/merchant/orders/${o.id}`)}
                card={(o) => (
                    <ListCard to={`/merchant/orders/${o.id}`} title={o.customer?.name} meta={`${o.reference} · ${ago(o.created_at)} · ${o.fulfilment === 'delivery' ? 'Delivery' : 'Pickup'}`} status={<StatusBadge status={o.status} />}
                        footer={NEXT_STEP[o.status] && <span className="flex items-center justify-between text-sm font-semibold text-brand-700">{NEXT_STEP[o.status]}<Icon name="chevron" className="h-4 w-4" /></span>}>
                        <p className="line-clamp-2">{o.items.map((i) => `${i.product_name} ×${i.quantity}`).join(', ')}</p>
                        {Number(o.total_amount) > 0 && <p className="mt-1 font-bold text-slate-900">{money(o.total_amount)}</p>}
                    </ListCard>
                )}
                columns={[
                { key: 'reference', label: 'Order', render: (o) => <><b className="text-slate-900">{o.reference}</b><span className="block text-xs text-slate-400">{ago(o.created_at)}</span></> },
                { key: 'customer', label: 'Customer', render: (o) => o.customer?.name },
                { key: 'items', label: 'Items', render: (o) => <span className="line-clamp-2 min-w-48">{o.items.map((i) => `${i.product_name} ×${i.quantity}`).join(', ')}</span> },
                { key: 'fulfilment', label: 'Mode', render: (o) => label(o.fulfilment) },
                { key: 'total', label: 'Total', render: (o) => money(o.total_amount) },
                { key: 'status', label: 'Status', render: (o) => <StatusBadge status={o.status} /> },
            ]} />
        </div>
    );
}

function QuoteModal({ order, onClose, onDone }) {
    const toast = useToast();
    const [items, setItems] = useState(order.items.map((i) => ({ id: i.id, unit_price: i.unit_price, quantity: i.quantity, variant: i.variant || '', colour: i.colour || '', model_note: i.model_note || '' })));
    const [fulfilment, setFulfilment] = useState(order.fulfilment);
    const [fee, setFee] = useState(order.fulfilment === 'delivery' ? order.delivery_fee || order.shop?.delivery_fee || 0 : 0);
    const [note, setNote] = useState(order.merchant_note || '');
    const upd = (i, k, v) => setItems((xs) => xs.map((x, j) => (j === i ? { ...x, [k]: v } : x)));
    const total = items.reduce((s, i) => s + Number(i.unit_price || 0) * Number(i.quantity || 1), 0) + (fulfilment === 'delivery' ? Number(fee || 0) : 0);
    const act = useAction(() => post(`/merchant/orders/${order.id}/quote`, { items, fulfilment, delivery_fee: fulfilment === 'delivery' ? fee : 0, merchant_note: note }), { onSuccess: () => { toast('Quotation sent to customer'); onDone(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Modal open onClose={onClose} size="max-w-2xl" title="Confirm availability & final price" footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button busy={act.busy} onClick={() => act.run()}>Send quotation · {money(total)}</Button></>}>
            <div className="space-y-4">
                {order.items.map((it, i) => (
                    <div key={it.id} className="rounded-xl border border-slate-200 p-3">
                        <p className="mb-2 font-semibold text-slate-900">{it.product_name} {it.product && <span className="text-xs font-normal text-slate-500">· in stock: {it.product.stock_quantity}</span>}</p>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <Field label="Unit price ₹"><Input type="number" min="0" value={items[i].unit_price} onChange={(e) => upd(i, 'unit_price', e.target.value)} /></Field>
                            <Field label="Qty"><Input type="number" min="1" value={items[i].quantity} onChange={(e) => upd(i, 'quantity', e.target.value)} /></Field>
                            <Field label="Variant"><Input value={items[i].variant} onChange={(e) => upd(i, 'variant', e.target.value)} placeholder="8GB/128GB" /></Field>
                            <Field label="Colour"><Input value={items[i].colour} onChange={(e) => upd(i, 'colour', e.target.value)} /></Field>
                            <Field label="Exact model / notes" className="col-span-2 sm:col-span-4"><Input value={items[i].model_note} onChange={(e) => upd(i, 'model_note', e.target.value)} /></Field>
                        </div>
                    </div>
                ))}
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Fulfilment"><Select value={fulfilment} onChange={(e) => setFulfilment(e.target.value)} options={['pickup', 'delivery']} /></Field>
                    {fulfilment === 'delivery' && <Field label="Delivery fee ₹"><Input type="number" min="0" value={fee} onChange={(e) => setFee(e.target.value)} /></Field>}
                </div>
                <Field label="Message to customer"><Textarea rows={2} value={note} onChange={(e) => setNote(e.target.value)} placeholder="e.g. Blue not available, black confirmed. Ready in 1 hour." /></Field>
            </div>
        </Modal>
    );
}

function PaymentModal({ order, onClose, onDone }) {
    const toast = useToast();
    const [method, setMethod] = useState(order.payment_method || 'cod');
    const paid = order.payments.reduce((s, p) => s + Number(p.amount), 0);
    const [amount, setAmount] = useState(Math.max(0, Number(order.total_amount) - paid));
    const [ref, setRef] = useState('');
    const act = useAction(() => post(`/merchant/orders/${order.id}/payments`, { method, amount, upi_reference: method === 'upi' ? ref : undefined }), { onSuccess: () => { toast('Payment recorded'); onDone(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Modal open onClose={onClose} title="Record payment" footer={<Button busy={act.busy} onClick={() => act.run()}>Record</Button>}>
            <div className="space-y-3">
                <Alert tone="blue" icon="rupee">Record payments you received in person or by UPI. Bhatkal Mobile Hub does not collect or transfer money in this phase.</Alert>
                <Field label="Method"><Select value={method} onChange={(e) => setMethod(e.target.value)} options={[{ value: 'cod', label: 'Cash on delivery' }, { value: 'pickup_payment', label: 'Paid at pickup' }, { value: 'upi', label: 'UPI' }]} /></Field>
                <Field label="Amount ₹"><Input type="number" value={amount} onChange={(e) => setAmount(e.target.value)} /></Field>
                {method === 'upi' && <Field label="UPI transaction reference" required error={act.errors.upi_reference}><Input value={ref} onChange={(e) => setRef(e.target.value)} placeholder="12-digit UTR" /></Field>}
            </div>
        </Modal>
    );
}

export function MerchantOrderDetail() {
    const { id } = useParams();
    const toast = useToast();
    const state = useApi(`/merchant/orders/${id}`);
    const [dialog, setDialog] = useState(null);
    const act = useAction((path, body) => post(`/merchant/orders/${id}/${path}`, body), { onSuccess: () => { setDialog(null); state.reload(); toast('Order updated'); }, onError: (e) => toast(e.message, 'error') });
    useEffect(() => { setDialog(null); }, [id]);

    return (
        <Async state={state}>
            {({ order: o, whatsapp_customer_link, whatsapp_confirmation_link }) => {
                const next = {
                    requested: [['quote', 'Confirm details & price', 'primary'], ['reject', 'Not available', 'secondary']],
                    quoted: [['quote', 'Revise quotation', 'secondary']],
                    accepted: [['confirm', 'Confirm order', 'primary']],
                    confirmed: [['ready', o.fulfilment === 'delivery' ? 'Mark out for delivery' : 'Mark ready for pickup', 'primary']],
                    ready: [['handover', o.fulfilment === 'delivery' ? 'Mark delivered' : 'Mark collected', 'primary']],
                }[o.status] || [];
                const canCancel = ['requested', 'quoted', 'accepted', 'confirmed', 'ready'].includes(o.status);
                const canPay = ['accepted', 'confirmed', 'ready', 'handed_over', 'completed'].includes(o.status) && o.payment_status !== 'paid';
                return (
                    <div className="space-y-4">
                        <PageHeader back="/merchant/orders" title={`Order ${o.reference}`} subtitle={`Received ${dateTime(o.created_at)}`} actions={<StatusBadge status={o.status} />} />
                        {o.status === 'requested' && <Alert tone="amber" icon="clock" title="Check availability">Confirm exact model, variant, colour, price and delivery fee. The customer must accept before you can confirm.</Alert>}
                        {o.status === 'quoted' && <Alert tone="blue" icon="clock" title="Waiting for the customer to accept your quotation" />}
                        {o.status === 'handed_over' && <Alert tone="blue" icon="clock" title="Waiting for the customer to confirm receipt" />}
                        <div className="flex flex-wrap gap-2">
                            {next.map(([a, t, v]) => <Button key={a} variant={v} onClick={() => setDialog(a)}>{t}</Button>)}
                            {canPay && <Button variant="secondary" onClick={() => setDialog('payment')}><Icon name="rupee" className="h-4 w-4" />Record payment</Button>}
                            <WhatsAppButton href={whatsapp_customer_link}>Message customer</WhatsAppButton>
                            {whatsapp_confirmation_link && <WhatsAppButton href={whatsapp_confirmation_link}>Send confirmation</WhatsAppButton>}
                            {canCancel && <Button variant="ghost" onClick={() => setDialog('cancel')}>Cancel order</Button>}
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                            <Section title="Items">
                                <ul className="divide-y divide-slate-100">{o.items.map((i) => (
                                    <li key={i.id} className="flex justify-between gap-3 py-2.5 text-sm"><div><p className="font-semibold text-slate-900">{i.product_name}</p><p className="text-slate-500">Qty {i.quantity}{i.variant && ` · ${i.variant}`}{i.colour && ` · ${i.colour}`}{i.model_note && ` · ${i.model_note}`}{i.product && ` · SKU ${i.product.sku} · stock ${i.product.stock_quantity}`}</p></div><b>{money(i.line_total)}</b></li>
                                ))}</ul>
                                <dl className="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm">
                                    <div className="flex justify-between"><dt className="text-slate-500">Products</dt><dd>{money(o.product_amount)}</dd></div>
                                    <div className="flex justify-between"><dt className="text-slate-500">Delivery fee</dt><dd>{money(o.delivery_fee)}</dd></div>
                                    <div className="flex justify-between font-bold"><dt>Order total</dt><dd>{money(o.total_amount)}</dd></div>
                                    {o.confirmed_at && <><div className="flex justify-between text-slate-500"><dt>Platform commission ({o.commission_type === 'percentage' ? `${o.commission_rate}%` : label(o.commission_type)})</dt><dd>−{money(o.commission_amount)}</dd></div>
                                        {Number(o.refund_amount) > 0 && <div className="flex justify-between text-slate-500"><dt>Refunds</dt><dd>−{money(o.refund_amount)}</dd></div>}
                                        {Number(o.adjustment_amount) !== 0 && <div className="flex justify-between text-slate-500"><dt>Adjustments</dt><dd>{money(o.adjustment_amount)}</dd></div>}
                                        <div className="flex justify-between font-semibold text-brand-700"><dt>Your payable</dt><dd>{money(o.merchant_payable)}</dd></div></>}
                                </dl>
                                {o.customer_note && <p className="mt-3 rounded-xl bg-amber-50 p-3 text-sm"><b>Customer note:</b> {o.customer_note}</p>}
                            </Section>
                            <div className="space-y-4">
                                <Section title="Customer"><DL items={[['Name', o.customer.name], ['Phone', <a key="p" className="text-brand-700" href={`tel:+91${o.customer.phone}`}>+91 {o.customer.phone}</a>], ['Fulfilment', label(o.fulfilment)], o.fulfilment === 'delivery' && ['Deliver to', `${o.delivery_address}${o.delivery_area ? ', ' + o.delivery_area.name : ''}`]]} /></Section>
                                <Section title="Payment"><DL items={[['Method', label(o.payment_method)], ['Status', <StatusBadge key="s" status={o.payment_status} />]]} />
                                    {o.payments.length > 0 && <ul className="mt-3 space-y-1 text-xs text-slate-600">{o.payments.map((p) => <li key={p.id}>{money(p.amount)} · {label(p.method)}{p.upi_reference && ` · ${p.upi_reference}`} · {dateTime(p.paid_at)}</li>)}</ul>}
                                </Section>
                            </div>
                        </div>
                        <Section title="Timeline"><Timeline items={o.histories} /></Section>

                        {dialog === 'quote' && <QuoteModal order={o} onClose={() => setDialog(null)} onDone={() => { setDialog(null); state.reload(); }} />}
                        {dialog === 'payment' && <PaymentModal order={o} onClose={() => setDialog(null)} onDone={() => { setDialog(null); state.reload(); }} />}
                        <ConfirmDialog open={dialog === 'confirm'} onClose={() => setDialog(null)} busy={act.busy} title="Confirm this order?" message="Stock will be reserved and the customer notified that the order is confirmed." confirmText="Confirm order" onConfirm={() => act.run('confirm')} />
                        <ConfirmDialog open={dialog === 'ready'} onClose={() => setDialog(null)} busy={act.busy} title="Mark as ready?" confirmText="Mark ready" onConfirm={() => act.run('ready')} />
                        <ConfirmDialog open={dialog === 'handover'} onClose={() => setDialog(null)} busy={act.busy} title={o.fulfilment === 'delivery' ? 'Mark as delivered?' : 'Mark as collected?'} message="The customer will be asked to confirm receipt to complete the order." confirmText="Yes" onConfirm={() => act.run('handover')} />
                        <ConfirmDialog open={dialog === 'reject'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason title="Decline order request" confirmText="Decline" onConfirm={(reason) => act.run('reject', { reason })} />
                        <ConfirmDialog open={dialog === 'cancel'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason title="Cancel order" message="Reserved stock is returned automatically." confirmText="Cancel order" onConfirm={(reason) => act.run('cancel', { reason })} />
                    </div>
                );
            }}
        </Async>
    );
}
