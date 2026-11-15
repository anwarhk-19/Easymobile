import { useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable, { Toolbar } from '../../components/DataTable';
import { Alert, Async, Badge, Button, Checkbox, ConfirmDialog, Field, Input, Modal, PageHeader, Section, Select, Stars, StatusBadge, Tabs, Textarea } from '../../components/ui';
import { del, post, put } from '../../lib/api';
import { useAction, useApi, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { date, dateTime, label, money } from '../../lib/format';

export function Reviews() {
    const toast = useToast();
    const [status, setStatus] = useState('pending');
    const [page, setPage] = useState(1);
    const state = useApi('/admin/reviews', { status, page });
    const act = useAction((id, s) => post(`/admin/reviews/${id}/moderate`, { status: s }), { onSuccess: () => { toast('Review moderated'); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <div>
            <PageHeader title="Reviews & ratings" subtitle="Only customers with a completed order or repair can review. Ratings count once approved." />
            <Tabs className="mb-4" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['pending', 'Pending'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['', 'All']]} />
            <DataTable state={state} onPage={setPage} empty="No reviews" emptyIcon="star" columns={[
                { key: 'for', label: 'For', render: (r) => <><b>{r.reviewable_name}</b><span className="block text-xs text-slate-400">{label(r.reviewable_type)} · {r.order?.reference || r.repair_request?.reference}</span></> },
                { key: 'rating', label: 'Rating', render: (r) => <Stars value={r.rating} /> }, { key: 'comment', label: 'Review', render: (r) => <span className="block max-w-sm text-slate-700">{r.comment || '—'}</span> },
                { key: 'customer', label: 'By', render: (r) => <>{r.customer?.name}<span className="block text-xs text-slate-400">{date(r.created_at)}</span></> },
                { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                { key: 'a', label: '', render: (r) => <div className="flex gap-1">{r.status !== 'approved' && <Button size="sm" onClick={() => act.run(r.id, 'approved')}>Approve</Button>}{r.status !== 'rejected' && <Button size="sm" variant="ghost" onClick={() => act.run(r.id, 'rejected')}>Reject</Button>}</div> },
            ]} />
        </div>
    );
}

export function Refunds() {
    const toast = useToast();
    const [status, setStatus] = useState('requested');
    const [page, setPage] = useState(1);
    const [target, setTarget] = useState(null);
    const [form, setForm] = useState({ status: 'processed', method: 'upi', reference: '', admin_note: '' });
    const state = useApi('/admin/refunds', { status, page });
    const act = useAction(() => post(`/admin/refunds/${target.id}/process`, form), { onSuccess: () => { toast('Refund updated'); setTarget(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <div>
            <PageHeader title="Refunds & cancellations" subtitle="Raise refunds from an order page. Processing records the refund; money is returned manually." />
            <Tabs className="mb-4" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['requested', 'Requested'], ['approved', 'Approved'], ['processed', 'Processed'], ['rejected', 'Rejected'], ['', 'All']]} />
            <DataTable state={state} onPage={setPage} empty="No refunds" emptyIcon="refresh" columns={[
                { key: 'order', label: 'Order', render: (r) => <Link className="font-semibold text-brand-700" to={`/admin/orders/${r.order_id}`}>{r.order?.reference}</Link> },
                { key: 'shop', label: 'Shop', render: (r) => r.order?.shop?.name }, { key: 'amount', label: 'Amount', render: (r) => <b>{money(r.amount)}</b> },
                { key: 'reason', label: 'Reason', render: (r) => <span className="block max-w-xs">{r.reason}</span> }, { key: 'by', label: 'Raised', render: (r) => <>{r.requester?.name}<span className="block text-xs text-slate-400">{dateTime(r.created_at)}</span></> },
                { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
                { key: 'a', label: '', render: (r) => ['requested', 'approved'].includes(r.status) && <Button size="sm" onClick={() => { setTarget(r); setForm({ status: 'processed', method: 'upi', reference: '', admin_note: '' }); }}>Process</Button> },
            ]} />
            <Modal open={!!target} onClose={() => setTarget(null)} title={`Refund ${money(target?.amount)}`} footer={<Button busy={act.busy} onClick={() => act.run()}>Save</Button>}>
                <div className="space-y-3">
                    <Field label="Decision"><Select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} options={[{ value: 'approved', label: 'Approve (to be paid)' }, { value: 'processed', label: 'Processed (paid to customer)' }, { value: 'rejected', label: 'Reject' }]} /></Field>
                    {form.status === 'processed' && <><Field label="Method"><Select value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })} options={['cash', 'upi', 'bank_transfer', 'store_credit']} /></Field><Field label="Reference"><Input value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} /></Field></>}
                    <Field label="Note"><Textarea value={form.admin_note} onChange={(e) => setForm({ ...form, admin_note: e.target.value })} /></Field>
                </div>
            </Modal>
        </div>
    );
}

export function Settlements() {
    const toast = useToast();
    const [tab, setTab] = useState('outstanding');
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState('');
    const outstanding = useApi(tab === 'outstanding' ? '/admin/settlements/outstanding' : null);
    const list = useApi(tab === 'history' ? '/admin/settlements' : null, { page, status });
    const [gen, setGen] = useState(null);
    const [pay, setPay] = useState(null);
    const generate = useAction(() => post('/admin/settlements', gen), { onSuccess: () => { toast('Settlement generated'); setGen(null); outstanding.reload(); setTab('history'); }, onError: (e) => toast(e.message, 'error') });
    const update = useAction(() => put(`/admin/settlements/${pay.id}`, pay.form), { onSuccess: () => { toast('Settlement updated'); setPay(null); list.reload(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <div>
            <PageHeader title="Merchant settlements" />
            <div className="mb-4"><Alert tone="blue" icon="rupee">Settlements record what each shop is owed. Funds are never transferred automatically — pay merchants manually and record the payment reference here.</Alert></div>
            <Tabs className="mb-4" value={tab} onChange={setTab} tabs={[['outstanding', 'Unsettled by shop'], ['history', 'Settlement statements']]} />
            {tab === 'outstanding' ? (
                <DataTable state={outstanding} rowKey="shop_id" empty="No unsettled completed orders" emptyIcon="rupee" columns={[
                    { key: 'shop', label: 'Shop', render: (r) => <b>{r.shop?.name}</b> }, { key: 'orders_count', label: 'Orders' },
                    { key: 'gross', label: 'Gross', render: (r) => money(r.gross_amount) }, { key: 'fee', label: 'Platform fee', render: (r) => money(r.platform_fee) },
                    { key: 'adj', label: 'Adjustments', render: (r) => money(r.adjustments) }, { key: 'ref', label: 'Refunds', render: (r) => money(r.refunds) },
                    { key: 'net', label: 'Net payable', render: (r) => <b>{money(r.net_payable)}</b> }, { key: 'range', label: 'Completed', render: (r) => `${date(r.oldest)} – ${date(r.newest)}` },
                    { key: 'a', label: '', render: (r) => <Button size="sm" onClick={() => setGen({ shop_id: r.shop_id, name: r.shop?.name, from: r.oldest?.slice(0, 10), to: new Date().toISOString().slice(0, 10) })}>Generate</Button> },
                ]} />
            ) : (
                <>
                    <Toolbar><Select value={status} onChange={(e) => setStatus(e.target.value)} placeholder="Any status" options={['pending', 'processing', 'paid', 'on_hold']} /></Toolbar>
                    <DataTable state={list} onPage={setPage} empty="No settlements yet" emptyIcon="rupee" columns={[
                        { key: 'reference', label: 'Reference', render: (s) => <b>{s.reference}</b> }, { key: 'shop', label: 'Shop', render: (s) => s.shop?.name },
                        { key: 'period', label: 'Period', render: (s) => `${date(s.period_start)} – ${date(s.period_end)}` }, { key: 'orders_count', label: 'Orders' },
                        { key: 'gross', label: 'Gross', render: (s) => money(s.gross_amount) }, { key: 'fee', label: 'Fee', render: (s) => money(s.platform_fee) }, { key: 'refunds', label: 'Refunds', render: (s) => money(s.refunds) },
                        { key: 'net', label: 'Net payable', render: (s) => <b>{money(s.net_payable)}</b> }, { key: 'status', label: 'Status', render: (s) => <StatusBadge status={s.status} /> },
                        { key: 'settled', label: 'Settled', render: (s) => (s.settled_at ? <>{date(s.settled_at)}<span className="block text-xs text-slate-400">{s.payment_reference}</span></> : '—') },
                        { key: 'a', label: '', render: (s) => s.status !== 'paid' && <Button size="sm" variant="secondary" onClick={() => setPay({ id: s.id, form: { status: 'paid', payment_reference: '', notes: '' } })}>Update</Button> },
                    ]} />
                </>
            )}
            <Modal open={!!gen} onClose={() => setGen(null)} title={`Generate settlement · ${gen?.name}`} footer={<Button busy={generate.busy} onClick={() => generate.run()}>Generate statement</Button>}>
                {gen && <div className="grid grid-cols-2 gap-3"><Field label="From"><Input type="date" value={gen.from} onChange={(e) => setGen({ ...gen, from: e.target.value })} /></Field><Field label="To"><Input type="date" value={gen.to} onChange={(e) => setGen({ ...gen, to: e.target.value })} /></Field><p className="col-span-2 text-sm text-slate-500">Includes completed orders in this period that are not in any settlement yet.</p></div>}
            </Modal>
            <Modal open={!!pay} onClose={() => setPay(null)} title="Update settlement" footer={<Button busy={update.busy} onClick={() => update.run()}>Save</Button>}>
                {pay && <div className="space-y-3">
                    <Field label="Status"><Select value={pay.form.status} onChange={(e) => setPay({ ...pay, form: { ...pay.form, status: e.target.value } })} options={['pending', 'processing', 'paid', 'on_hold']} /></Field>
                    <Field label="Payment reference (UTR / cheque no.)" error={update.errors.payment_reference}><Input value={pay.form.payment_reference} onChange={(e) => setPay({ ...pay, form: { ...pay.form, payment_reference: e.target.value } })} /></Field>
                    <Field label="Notes"><Textarea value={pay.form.notes} onChange={(e) => setPay({ ...pay, form: { ...pay.form, notes: e.target.value } })} /></Field>
                </div>}
            </Modal>
        </div>
    );
}

export function Commissions() {
    const toast = useToast();
    const meta = useMeta();
    const state = useApi('/admin/commissions');
    const shops = useApi('/admin/shops', { status: 'approved' });
    const [edit, setEdit] = useState(null);
    const [remove, setRemove] = useState(null);
    const save = useAction(() => (edit.id ? put(`/admin/commissions/${edit.id}`, edit) : post('/admin/commissions', edit)), { onSuccess: () => { toast('Commission rule saved'); setEdit(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    const destroy = useAction(() => del(`/admin/commissions/${remove.id}`), { onSuccess: () => { setRemove(null); state.reload(); } });
    const fmt = (c) => (c.type === 'free' ? 'Free listing' : c.type === 'percentage' ? `${Number(c.value)}% per order` : `${money(c.value)} flat per order`);
    return (
        <div>
            <PageHeader title="Commission rules" subtitle="Precedence: shop rule → category rule → global rule. No rule means free. Commission is calculated on product value, snapshotted when the shop confirms an order." actions={<Button onClick={() => setEdit({ scope: 'global', type: 'percentage', value: 2, is_active: true })}>Add rule</Button>} />
            <DataTable state={state} empty="No rules — all listings are free" emptyIcon="rupee" columns={[
                { key: 'scope', label: 'Applies to', render: (c) => <><Badge>{label(c.scope)}</Badge> {c.shop?.name || c.category?.name || 'All orders'}</> },
                { key: 'rule', label: 'Rule', render: (c) => <b>{fmt(c)}</b> }, { key: 'effective_from', label: 'From', render: (c) => date(c.effective_from) },
                { key: 'notes', label: 'Notes' }, { key: 'active', label: 'Status', render: (c) => <StatusBadge status={c.is_active ? 'active' : 'inactive'} /> },
                { key: 'a', label: '', render: (c) => <div className="flex gap-1"><Button size="sm" variant="secondary" onClick={() => setEdit({ ...c, effective_from: c.effective_from?.slice(0, 10) || '' })}>Edit</Button><Button size="sm" variant="ghost" onClick={() => setRemove(c)}>Delete</Button></div> },
            ]} />
            <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? 'Edit rule' : 'New commission rule'} footer={<Button busy={save.busy} onClick={() => save.run()}>Save</Button>}>
                {edit && <div className="space-y-3">
                    <Field label="Scope"><Select value={edit.scope} onChange={(e) => setEdit({ ...edit, scope: e.target.value })} options={['global', 'category', 'shop']} /></Field>
                    {edit.scope === 'category' && <Field label="Category" error={save.errors.category_id}><Select value={edit.category_id || ''} onChange={(e) => setEdit({ ...edit, category_id: e.target.value })} placeholder="Select" options={(meta?.categories || []).map((c) => ({ value: c.id, label: c.name }))} /></Field>}
                    {edit.scope === 'shop' && <Field label="Shop" error={save.errors.shop_id}><Select value={edit.shop_id || ''} onChange={(e) => setEdit({ ...edit, shop_id: e.target.value })} placeholder="Select" options={(shops.data?.data || []).map((s) => ({ value: s.id, label: s.name }))} /></Field>}
                    <Field label="Type"><Select value={edit.type} onChange={(e) => setEdit({ ...edit, type: e.target.value })} options={[{ value: 'percentage', label: 'Percentage per order' }, { value: 'flat', label: 'Flat fee per order' }, { value: 'free', label: 'Free listing' }]} /></Field>
                    {edit.type !== 'free' && <Field label={edit.type === 'percentage' ? 'Rate %' : 'Amount ₹'} error={save.errors.value}><Input type="number" step="0.01" value={edit.value} onChange={(e) => setEdit({ ...edit, value: e.target.value })} /></Field>}
                    <Field label="Effective from (optional)"><Input type="date" value={edit.effective_from || ''} onChange={(e) => setEdit({ ...edit, effective_from: e.target.value })} /></Field>
                    <Field label="Notes"><Input value={edit.notes || ''} onChange={(e) => setEdit({ ...edit, notes: e.target.value })} /></Field>
                    <Checkbox label="Active" checked={!!edit.is_active} onChange={(e) => setEdit({ ...edit, is_active: e.target.checked })} />
                    <p className="text-xs text-slate-500">Merchant subscriptions are planned for a later phase.</p>
                </div>}
            </Modal>
            <ConfirmDialog open={!!remove} onClose={() => setRemove(null)} busy={destroy.busy} tone="danger" title="Delete rule?" message="Existing orders keep their recorded commission." confirmText="Delete" onConfirm={() => destroy.run()} />
        </div>
    );
}
