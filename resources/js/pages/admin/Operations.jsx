import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import DataTable, { Toolbar } from '../../components/DataTable';
import { Alert, Async, Badge, Button, Checkbox, ConfirmDialog, DL, Field, Input, Modal, PageHeader, Section, Select, StatusBadge, Tabs, Textarea, Timeline } from '../../components/ui';
import { TicketThread } from '../customer/Support';
import { AuthImage } from '../customer/Repairs';
import { post, put } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction, useApi, useDebounced, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { ago, dateTime, label, money } from '../../lib/format';

export function AdminOrders() {
    const navigate = useNavigate();
    const [status, setStatus] = useState(new URLSearchParams(window.location.search).get('status') || '');
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [range, setRange] = useState({ from: '', to: '' });
    const [page, setPage] = useState(1);
    const state = useApi('/admin/orders', { status, q: dq, page, ...range });
    return (
        <div>
            <PageHeader title="Orders" />
            <Tabs className="mb-3" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={['', 'requested', 'quoted', 'accepted', 'confirmed', 'ready', 'handed_over', 'completed', 'cancelled', 'rejected'].map((s) => [s, s ? label(s) : 'All'])} />
            <Toolbar><Input type="search" placeholder="Reference" value={q} onChange={(e) => setQ(e.target.value)} /><Input type="date" aria-label="From" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} /><Input type="date" aria-label="To" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No orders" emptyIcon="bag" onRowClick={(o) => navigate(`/admin/orders/${o.id}`)} columns={[
                { key: 'reference', label: 'Order', render: (o) => <><b>{o.reference}</b><span className="block text-xs text-slate-400">{ago(o.created_at)}</span></> },
                { key: 'shop', label: 'Shop', render: (o) => o.shop?.name }, { key: 'customer', label: 'Customer', render: (o) => o.customer?.name },
                { key: 'total', label: 'Total', render: (o) => money(o.total_amount) }, { key: 'commission', label: 'Commission', render: (o) => money(o.commission_amount) },
                { key: 'payment', label: 'Payment', render: (o) => <StatusBadge status={o.payment_status} /> }, { key: 'status', label: 'Status', render: (o) => <StatusBadge status={o.status} /> },
            ]} />
        </div>
    );
}

export function AdminOrderDetail() {
    const { id } = useParams();
    const toast = useToast();
    const { user } = useAuth();
    const state = useApi(`/admin/orders/${id}`);
    const [dialog, setDialog] = useState(null);
    const [form, setForm] = useState({ amount: '', note: '', reason: '' });
    const act = useAction((path, body) => post(path, body), { onSuccess: () => { setDialog(null); setForm({ amount: '', note: '', reason: '' }); state.reload(); toast('Saved'); }, onError: (e) => toast(e.message, 'error') });
    const admin = user.role === 'admin';
    return (
        <Async state={state}>
            {({ order: o }) => (
                <div className="space-y-4">
                    <PageHeader back="/admin/orders" title={`Order ${o.reference}`} subtitle={`${o.shop.name} · ${o.customer.name}`} actions={<StatusBadge status={o.status} />} />
                    {admin && <div className="flex flex-wrap gap-2">
                        {o.status === 'handed_over' && <Button onClick={() => setDialog('complete')}>Mark completed</Button>}
                        {['requested', 'quoted', 'accepted', 'confirmed', 'ready', 'handed_over'].includes(o.status) && <Button variant="danger" onClick={() => setDialog('cancel')}>Cancel order</Button>}
                        {o.confirmed_at && <Button variant="secondary" onClick={() => setDialog('refund')}>Raise refund</Button>}
                        {o.status === 'completed' && <Button variant="secondary" onClick={() => setDialog('adjust')}>Adjust payable</Button>}
                    </div>}
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Section title="Items">
                            <ul className="divide-y divide-slate-100 text-sm">{o.items.map((i) => <li key={i.id} className="flex justify-between py-2"><span>{i.product_name} ×{i.quantity}{i.colour && ` · ${i.colour}`}{i.variant && ` · ${i.variant}`}</span><b>{money(i.line_total)}</b></li>)}</ul>
                        </Section>
                        <Section title="Financials">
                            <DL items={[['Product amount', money(o.product_amount)], ['Delivery fee', money(o.delivery_fee)], ['Order total', money(o.total_amount)], ['Commission', `${money(o.commission_amount)} (${o.commission_type === 'percentage' ? o.commission_rate + '%' : label(o.commission_type) || '—'})`],
                                ['Adjustments', money(o.adjustment_amount)], ['Refunds', money(o.refund_amount)], ['Merchant payable', <b key="m">{money(o.merchant_payable)}</b>], ['Payment', `${label(o.payment_method)} · ${label(o.payment_status)}`], ['Settlement', o.settlement ? `${o.settlement.reference} (${label(o.settlement.status)})` : 'Not settled']]} />
                        </Section>
                    </div>
                    {o.payments.length > 0 && <Section title="Payments recorded"><ul className="text-sm">{o.payments.map((p) => <li key={p.id}>{money(p.amount)} · {label(p.method)}{p.upi_reference && ` · UPI ${p.upi_reference}`} · {dateTime(p.paid_at)}</li>)}</ul></Section>}
                    {o.refunds.length > 0 && <Section title="Refunds"><ul className="text-sm">{o.refunds.map((r) => <li key={r.id} className="flex justify-between py-1"><span>{money(r.amount)} · {r.reason}</span><StatusBadge status={r.status} /></li>)}</ul><Link className="mt-2 inline-block text-sm font-semibold text-brand-700" to="/admin/refunds">Manage refunds</Link></Section>}
                    <Section title="Customer & delivery"><DL items={[['Customer', `${o.customer.name} (+91 ${o.customer.phone})`], ['Shop', `${o.shop.name} (+91 ${o.shop.phone})`], ['Fulfilment', label(o.fulfilment)], o.delivery_address && ['Address', o.delivery_address], o.cancel_reason && ['Cancel reason', o.cancel_reason]]} /></Section>
                    <Section title="Timeline"><Timeline items={o.histories} /></Section>

                    <ConfirmDialog open={dialog === 'complete'} onClose={() => setDialog(null)} busy={act.busy} title="Complete on the customer's behalf?" message="Use only when the customer confirmed receipt by phone or WhatsApp." confirmText="Complete" onConfirm={() => act.run(`/admin/orders/${id}/complete`)} />
                    <ConfirmDialog open={dialog === 'cancel'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason title="Cancel order" confirmText="Cancel order" onConfirm={(reason) => act.run(`/admin/orders/${id}/cancel`, { reason })} />
                    <Modal open={dialog === 'refund'} onClose={() => setDialog(null)} title="Raise a refund" footer={<Button busy={act.busy} onClick={() => act.run('/admin/refunds', { order_id: o.id, amount: form.amount, reason: form.reason })}>Create refund</Button>}>
                        <div className="space-y-3"><Field label="Amount ₹" error={act.errors.amount}><Input type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field><Field label="Reason" error={act.errors.reason}><Textarea value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></Field></div>
                    </Modal>
                    <Modal open={dialog === 'adjust'} onClose={() => setDialog(null)} title="Adjust merchant payable" footer={<Button busy={act.busy} onClick={() => act.run(`/admin/orders/${id}/adjust`, { amount: form.amount, note: form.note })}>Apply</Button>}>
                        <div className="space-y-3"><Field label="Amount ₹ (negative to deduct)" error={act.errors.amount}><Input type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field><Field label="Note" error={act.errors.note}><Textarea value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} /></Field></div>
                    </Modal>
                </div>
            )}
        </Async>
    );
}

export function AdminRepairs() {
    const navigate = useNavigate();
    const [status, setStatus] = useState('');
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const state = useApi('/admin/repairs', { status, q: dq, page });
    return (
        <div>
            <PageHeader title="Repair jobs" />
            <Tabs className="mb-3" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={['', 'submitted', 'accepted', 'quoted', 'approved', 'in_progress', 'ready_for_handover', 'completed', 'cancelled', 'rejected'].map((s) => [s, s ? label(s) : 'All'])} />
            <Toolbar><Input type="search" placeholder="Reference" value={q} onChange={(e) => setQ(e.target.value)} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No repairs" emptyIcon="wrench" onRowClick={(r) => navigate(`/admin/repairs/${r.id}`)} columns={[
                { key: 'reference', label: 'Job', render: (r) => <><b>{r.reference}</b><span className="block text-xs text-slate-400">{ago(r.created_at)}</span></> },
                { key: 'device', label: 'Device', render: (r) => `${r.brand?.name} ${r.phone_model?.name || r.device_model_text || ''}` }, { key: 'cat', label: 'Repair', render: (r) => r.repair_category?.name },
                { key: 'tech', label: 'Technician', render: (r) => r.technician?.name }, { key: 'customer', label: 'Customer', render: (r) => r.customer?.name },
                { key: 'amount', label: 'Final', render: (r) => money(r.final_amount) }, { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
            ]} />
        </div>
    );
}

export function AdminRepairDetail() {
    const { id } = useParams();
    const toast = useToast();
    const { user } = useAuth();
    const [reveal, setReveal] = useState(false);
    const state = useApi(`/admin/repairs/${id}`, reveal ? { reveal_imei: 1 } : null);
    const [dialog, setDialog] = useState(false);
    const act = useAction((reason) => post(`/admin/repairs/${id}/cancel`, { reason }), { onSuccess: () => { setDialog(false); state.reload(); toast('Repair cancelled'); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Async state={state}>
            {({ repair: r, has_imei, imei }) => (
                <div className="space-y-4">
                    <PageHeader back="/admin/repairs" title={`Repair ${r.reference}`} subtitle={`${r.technician.name} · ${r.customer.name}`} actions={<><StatusBadge status={r.status} />{user.role === 'admin' && !['completed', 'cancelled', 'rejected'].includes(r.status) && <Button variant="danger" onClick={() => setDialog(true)}>Cancel job</Button>}</>} />
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Section title="Request"><DL items={[['Device', `${r.brand.name} ${r.phone_model?.name || r.device_model_text}`], ['Repair', r.repair_category.name], ['Issue', r.issue_description], ['Service mode', label(r.service_mode)], ['Consent', r.data_access_consent ? 'Given' : 'Not given'],
                            ['IMEI', !has_imei ? 'Not provided' : imei || (user.role === 'admin' ? <Button key="i" size="sm" variant="secondary" onClick={() => setReveal(true)}>Reveal (logged)</Button> : 'Restricted')]]} />
                            {r.images.length > 0 && <div className="mt-3 flex gap-2">{r.images.map((im) => <AuthImage key={im.id} url={im.url} />)}</div>}</Section>
                        <Section title="Quotations">{r.quotations.length ? <ul className="space-y-2 text-sm">{r.quotations.map((q) => <li key={q.id} className="rounded-xl border border-slate-200 p-3"><div className="flex justify-between"><b>v{q.version} · {money(q.total)}</b><StatusBadge status={q.status} /></div><p className="text-slate-600">{q.diagnosis}</p><p className="text-xs text-slate-500">{label(q.parts_type)} · {q.warranty_days}d warranty{q.change_reason && ` · Changed: ${q.change_reason}`}</p></li>)}</ul> : <p className="text-sm text-slate-500">None yet</p>}</Section>
                    </div>
                    {r.warranty && <Section title="Warranty"><DL items={[['Code', r.warranty.warranty_code], ['Valid', `${r.warranty.starts_at?.slice(0, 10)} → ${r.warranty.ends_at?.slice(0, 10)}`], ['Final amount', money(r.final_amount)]]} /></Section>}
                    <Section title="Timeline"><Timeline items={r.histories} /></Section>
                    <ConfirmDialog open={dialog} onClose={() => setDialog(false)} busy={act.busy} tone="danger" reason title="Cancel repair job" confirmText="Cancel job" onConfirm={(reason) => act.run(reason)} />
                </div>
            )}
        </Async>
    );
}

export function AdminTickets() {
    const navigate = useNavigate();
    const meta = useMeta();
    const init = new URLSearchParams(window.location.search);
    const [f, setF] = useState({ status: '', category: '', priority: '', escalated: init.get('escalated') || '', overdue: '', assigned: '', q: '' });
    const dq = useDebounced(f.q);
    const [page, setPage] = useState(1);
    const state = useApi('/admin/tickets', { ...f, q: dq, page });
    const upd = (k) => (e) => { setF({ ...f, [k]: e.target.type === 'checkbox' ? (e.target.checked ? '1' : '') : e.target.value }); setPage(1); };
    return (
        <div>
            <PageHeader title="Complaints & support" subtitle="Pilot SLA: acknowledge within one business day." />
            <Toolbar>
                <Input type="search" placeholder="Reference or subject" value={f.q} onChange={upd('q')} />
                <Select value={f.status} onChange={upd('status')} placeholder="Any status" options={['open', 'acknowledged', 'in_progress', 'escalated', 'resolved', 'closed']} />
                <Select value={f.category} onChange={upd('category')} placeholder="Any category" options={meta?.enums?.ticket_categories || []} />
                <Select value={f.priority} onChange={upd('priority')} placeholder="Any priority" options={['low', 'normal', 'high', 'urgent']} />
                <Checkbox label="Escalated" checked={!!f.escalated} onChange={upd('escalated')} />
                <Checkbox label="Overdue" checked={!!f.overdue} onChange={upd('overdue')} />
                <Checkbox label="Assigned to me" checked={f.assigned === 'me'} onChange={(e) => setF({ ...f, assigned: e.target.checked ? 'me' : '' })} />
            </Toolbar>
            <DataTable state={state} onPage={setPage} empty="No tickets" emptyIcon="ticket" onRowClick={(t) => navigate(`/admin/tickets/${t.id}`)} columns={[
                { key: 'reference', label: 'Ticket', render: (t) => <><b>{t.reference}</b><span className="block max-w-64 truncate text-xs text-slate-500">{t.subject}</span></> },
                { key: 'category', label: 'Category', render: (t) => label(t.category) }, { key: 'user', label: 'Raised by', render: (t) => <>{t.user?.name}<span className="block text-xs text-slate-400">{label(t.user?.role)}</span></> },
                { key: 'priority', label: 'Priority', render: (t) => <Badge tone={{ urgent: 'red', high: 'amber', normal: 'slate', low: 'slate' }[t.priority]}>{label(t.priority)}</Badge> },
                { key: 'sla', label: 'SLA', render: (t) => (t.acknowledged_at ? <Badge tone="green">Acknowledged</Badge> : t.is_overdue ? <Badge tone="red">Overdue</Badge> : <span className="text-xs">Due {dateTime(t.acknowledge_due_at)}</span>) },
                { key: 'assignee', label: 'Assigned', render: (t) => t.assignee?.name || '—' },
                { key: 'status', label: 'Status', render: (t) => <span className="flex gap-1"><StatusBadge status={t.status} />{t.is_escalated && t.status !== 'escalated' && <Badge tone="red">!</Badge>}</span> },
            ]} />
        </div>
    );
}

export function AdminTicketDetail() {
    const { id } = useParams();
    const toast = useToast();
    const state = useApi(`/admin/tickets/${id}`);
    const staff = useApi('/admin/tickets/staff');
    const [form, setForm] = useState(null);
    const save = useAction((body) => put(`/admin/tickets/${id}`, body), { onSuccess: () => { toast('Ticket updated'); setForm(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Async state={state}>
            {({ ticket: t }) => {
                const f = form || { status: t.status, priority: t.priority, assigned_to: t.assigned_to || '', resolution_notes: t.resolution_notes || '', escalate: false, escalation_reason: '' };
                const set = (k) => (e) => setForm({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });
                return (
                    <div className="space-y-4">
                        <PageHeader back="/admin/tickets" title={t.subject} subtitle={`${t.reference} · ${label(t.category)} · raised ${dateTime(t.created_at)} by ${t.user?.name} (${t.user?.phone || label(t.user?.role)})`} actions={<StatusBadge status={t.status} />} />
                        {t.is_escalated && <Alert tone="red" title="Escalated to administrator">{t.escalation_reason}</Alert>}
                        {!t.acknowledged_at && <Alert tone={t.is_overdue ? 'red' : 'amber'} icon="clock">{t.is_overdue ? 'Acknowledgement overdue' : 'Acknowledge by'} {dateTime(t.acknowledge_due_at)} — replying or changing status acknowledges it.</Alert>}
                        <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                            <TicketThread ticket={t} base="/admin/tickets" onReply={state.reload} staff />
                            <div className="space-y-4">
                                <Section title="Manage">
                                    <div className="space-y-3">
                                        <Field label="Status"><Select value={f.status} onChange={set('status')} options={['open', 'acknowledged', 'in_progress', 'escalated', 'resolved', 'closed']} /></Field>
                                        <Field label="Priority"><Select value={f.priority} onChange={set('priority')} options={['low', 'normal', 'high', 'urgent']} /></Field>
                                        <Field label="Assigned operator"><Select value={f.assigned_to} onChange={set('assigned_to')} placeholder="Unassigned" options={(staff.data || []).map((s) => ({ value: s.id, label: `${s.name} (${label(s.role)})` }))} /></Field>
                                        <Field label="Resolution notes" error={save.errors.resolution_notes}><Textarea rows={3} value={f.resolution_notes} onChange={set('resolution_notes')} /></Field>
                                        {!t.is_escalated && <><Checkbox label="Escalate to administrator (fraud, safety, privacy, repeated misconduct)" checked={f.escalate} onChange={set('escalate')} />{f.escalate && <Field label="Escalation reason" error={save.errors.escalation_reason}><Input value={f.escalation_reason} onChange={set('escalation_reason')} /></Field>}</>}
                                        <Button className="w-full" busy={save.busy} onClick={() => save.run({ ...f, assigned_to: f.assigned_to || undefined, escalate: f.escalate || undefined })}>Save</Button>
                                    </div>
                                </Section>
                                <Section title="Related">
                                    <DL items={[['Order', t.order ? <Link key="o" className="text-brand-700" to={`/admin/orders/${t.order.id}`}>{t.order.reference} ({label(t.order.status)})</Link> : '—'], ['Repair', t.repair_request ? <Link key="r" className="text-brand-700" to={`/admin/repairs/${t.repair_request.id}`}>{t.repair_request.reference}</Link> : '—']]} />
                                </Section>
                            </div>
                        </div>
                    </div>
                );
            }}
        </Async>
    );
}
