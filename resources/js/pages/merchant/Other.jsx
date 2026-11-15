import { useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '../../components/DataTable';
import { Async, Button, Checkbox, Field, Input, Modal, PageHeader, Section, Stat, StatusBadge, Tabs, Textarea, WhatsAppButton } from '../../components/ui';
import { post } from '../../lib/api';
import { useAction, useApi } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { date, dateTime, label, minutes, money } from '../../lib/format';

export function MerchantEnquiries() {
    const [status, setStatus] = useState('new');
    const [page, setPage] = useState(1);
    const [replyTo, setReplyTo] = useState(null);
    const [reply, setReply] = useState('');
    const [close, setClose] = useState(false);
    const toast = useToast();
    const state = useApi('/merchant/enquiries', { status, page });
    const send = useAction(() => post(`/merchant/enquiries/${replyTo.id}/reply`, { reply, close }), { onSuccess: () => { toast('Reply sent'); setReplyTo(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });

    return (
        <div>
            <PageHeader title="Enquiries" subtitle="Questions from customers. An enquiry is not an order." />
            <Tabs className="mb-4" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['new', 'New'], ['responded', 'Replied'], ['converted', 'Became orders'], ['closed', 'Closed'], ['', 'All']]} />
            <Async state={state}>
                {(d) => d.data.length === 0 ? <DataTable state={{ data: [] }} columns={[]} empty="No enquiries here" emptyIcon="message" /> : (
                    <ul className="space-y-3">{d.data.map((e) => (
                        <li key={e.id} className="card p-4">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div><p className="font-semibold text-slate-900">{e.customer?.name} <span className="text-xs font-normal text-slate-400">· {e.reference} · {dateTime(e.created_at)}</span></p>
                                    {e.product && <p className="text-sm text-slate-600">{e.product.name} — {money(e.product.discount_price || e.product.price)} · stock {e.product.stock_quantity}</p>}{e.phone_model && <p className="text-xs text-slate-500">For: {e.phone_model.name}</p>}</div>
                                <StatusBadge status={e.status} />
                            </div>
                            <p className="mt-2 text-sm text-slate-800">“{e.message}”</p>
                            {e.merchant_reply && <p className="mt-2 rounded-xl bg-brand-50 p-2.5 text-sm"><b>Your reply:</b> {e.merchant_reply}</p>}
                            {e.order && <p className="mt-2 text-sm"><Link className="font-semibold text-brand-700" to={`/merchant/orders/${e.order.id}`}>Order {e.order.reference}</Link></p>}
                            <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => { setReplyTo(e); setReply(e.merchant_reply || ''); setClose(false); }}>Reply</Button><WhatsAppButton size="sm" href={e.whatsapp_link}>WhatsApp</WhatsAppButton></div>
                        </li>
                    ))}</ul>
                )}
            </Async>
            <Modal open={!!replyTo} onClose={() => setReplyTo(null)} title="Reply to enquiry" footer={<Button busy={send.busy} onClick={() => send.run()}>Send reply</Button>}>
                <div className="space-y-3"><p className="text-sm text-slate-600">“{replyTo?.message}”</p><Textarea rows={4} value={reply} onChange={(e) => setReply(e.target.value)} placeholder="Yes, available. Price ₹… Visit before 9 PM." /><Checkbox label="Close this enquiry" checked={close} onChange={(e) => setClose(e.target.checked)} /></div>
            </Modal>
        </div>
    );
}

export function DateRange({ value, onChange }) {
    return (
        <div className="flex flex-wrap items-end gap-2">
            <Field label="From"><Input type="date" value={value.from} onChange={(e) => onChange({ ...value, from: e.target.value })} /></Field>
            <Field label="To"><Input type="date" value={value.to} onChange={(e) => onChange({ ...value, to: e.target.value })} /></Field>
        </div>
    );
}

export const defaultRange = () => ({ from: new Date(Date.now() - 29 * 864e5).toISOString().slice(0, 10), to: new Date().toISOString().slice(0, 10) });

export function MerchantReports() {
    const [range, setRange] = useState(defaultRange);
    const state = useApi('/merchant/reports', range);
    return (
        <div className="space-y-4">
            <PageHeader title="Reports" actions={<DateRange value={range} onChange={setRange} />} />
            <Async state={state}>
                {(r) => (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Stat label="Enquiries" value={r.enquiries} hint={`Avg reply ${minutes(r.enquiry_response_minutes)}`} icon="message" />
                            <Stat label="Order requests" value={r.order_requests} icon="bag" tone="blue" />
                            <Stat label="Confirmed" value={r.confirmed_orders} icon="check" tone="blue" />
                            <Stat label="Completed" value={r.completed_orders} icon="check" tone="green" />
                            <Stat label="Cancelled" value={r.cancelled_orders} icon="x" tone="slate" />
                            <Stat label="Revenue" value={money(r.revenue)} icon="rupee" tone="green" />
                            <Stat label="Platform fee" value={money(r.commission)} icon="rupee" tone="slate" />
                            <Stat label="Net payable" value={money(r.net_payable)} icon="rupee" tone="brand" />
                        </div>
                        <Section title="Product performance">
                            <DataTable state={{ data: r.product_performance }} empty="No product activity" columns={[
                                { key: 'name', label: 'Product', render: (p) => <><b>{p.name}</b><span className="block text-xs text-slate-400">{p.sku}</span></> },
                                { key: 'views_count', label: 'Views' }, { key: 'enquiries', label: 'Enquiries' }, { key: 'units_sold', label: 'Units sold' },
                                { key: 'stock_quantity', label: 'Stock' }, { key: 'availability', label: 'Availability', render: (p) => <StatusBadge status={p.availability} /> },
                            ]} />
                        </Section>
                        <Section title="Stock movement">
                            <DataTable state={{ data: r.stock_movement }} empty="No stock changes in this period" columns={[
                                { key: 'created_at', label: 'When', render: (l) => dateTime(l.created_at) }, { key: 'product', label: 'Product', render: (l) => l.product?.name },
                                { key: 'reason', label: 'Reason', render: (l) => label(l.reason) }, { key: 'change', label: 'Change', render: (l) => <b className={l.change < 0 ? 'text-red-600' : 'text-emerald-700'}>{l.change > 0 ? '+' : ''}{l.change}</b> }, { key: 'new_quantity', label: 'New qty' },
                            ]} />
                        </Section>
                    </>
                )}
            </Async>
        </div>
    );
}

export function MerchantSettlements() {
    const [page, setPage] = useState(1);
    const state = useApi('/merchant/settlements', { page });
    return (
        <div>
            <PageHeader title="Settlements" subtitle="Statements of what the platform owes you for completed orders. Payouts are made manually and recorded here." />
            <DataTable state={state} onPage={setPage} empty="No settlements yet" emptyIcon="rupee" columns={[
                { key: 'reference', label: 'Reference', render: (s) => <b>{s.reference}</b> }, { key: 'period', label: 'Period', render: (s) => `${date(s.period_start)} – ${date(s.period_end)}` },
                { key: 'orders_count', label: 'Orders' }, { key: 'gross_amount', label: 'Gross', render: (s) => money(s.gross_amount) }, { key: 'platform_fee', label: 'Platform fee', render: (s) => money(s.platform_fee) },
                { key: 'adjustments', label: 'Adjust.', render: (s) => money(s.adjustments) }, { key: 'refunds', label: 'Refunds', render: (s) => money(s.refunds) },
                { key: 'net_payable', label: 'Net payable', render: (s) => <b>{money(s.net_payable)}</b> }, { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} /> },
                { key: 'settled_at', label: 'Settled', render: (s) => (s.settled_at ? <>{date(s.settled_at)}<span className="block text-xs text-slate-400">{s.payment_reference}</span></> : '—') },
            ]} />
        </div>
    );
}
