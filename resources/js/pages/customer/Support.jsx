import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import { Alert, Async, Badge, Button, Empty, Field, FileDrop, Input, PageHeader, Pagination, Section, Select, StatusBadge, Textarea } from '../../components/ui';
import { fetchPrivate, post } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction, useApi, useForm, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { dateTime, label } from '../../lib/format';

export function Tickets() {
    const [page, setPage] = useState(1);
    const state = useApi('/tickets', { page });
    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title="Support & complaints" subtitle="We acknowledge every complaint within one business day." actions={<Button as={Link} to="/support/new"><Icon name="plus" className="h-4 w-4" />New complaint</Button>} />
            <Async state={state}>
                {(d) => d.data.length === 0 ? <Empty icon="ticket" title="No support tickets">Wrong product, delayed delivery, repair or payment issue? Raise a complaint and our team will help.</Empty> : (
                    <><ul className="space-y-3">{d.data.map((t) => (
                        <li key={t.id}><Link to={`/support/${t.id}`} className="card flex items-center justify-between gap-3 p-4 hover:border-brand-500">
                            <div><p className="font-semibold text-slate-900">{t.subject}</p><p className="text-xs text-slate-400">{t.reference} · {label(t.category)} · {dateTime(t.created_at)}</p></div>
                            <StatusBadge status={t.status} />
                        </Link></li>
                    ))}</ul><Pagination meta={d} onPage={setPage} /></>
                )}
            </Async>
        </div>
    );
}

export function NewTicket() {
    const [params] = useSearchParams();
    const meta = useMeta();
    const { user } = useAuth();
    const toast = useToast();
    const navigate = useNavigate();
    const orders = useApi(user.role === 'customer' ? '/orders' : null);
    const repairs = useApi(user.role === 'customer' ? '/repairs' : null);
    const { values, field, set } = useForm({ category: params.get('category') || '', subject: '', description: '', order_id: params.get('order') || '', repair_request_id: params.get('repair') || '', priority: 'normal', attachments: [] });
    const submit = useAction(() => post('/tickets', { ...values, order_id: values.order_id || undefined, repair_request_id: values.repair_request_id || undefined }, { form: true }),
        { onSuccess: (r) => { toast(r.message); navigate(`/support/${r.ticket.id}`); }, onError: (e) => toast(e.message, 'error') });
    const e = submit.errors;
    return (
        <div className="mx-auto max-w-2xl">
            <PageHeader back="/support" title="Raise a complaint" />
            <form className="card space-y-4 p-5" onSubmit={(ev) => { ev.preventDefault(); submit.run(); }}>
                <Field label="What is the issue about?" required error={e.category}><Select placeholder="Select category" options={meta?.enums?.ticket_categories || []} {...field('category')} /></Field>
                {['fraud', 'safety_concern', 'privacy_violation', 'seller_misconduct'].includes(values.category) && <Alert tone="red" title="This will be escalated to the administrator immediately." />}
                {user.role === 'customer' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Related order"><Select placeholder="None" options={(orders.data?.data || []).map((o) => ({ value: o.id, label: `${o.reference} · ${o.shop.name}` }))} {...field('order_id')} /></Field>
                        <Field label="Related repair"><Select placeholder="None" options={(repairs.data?.data || []).map((r) => ({ value: r.id, label: `${r.reference} · ${r.technician.name}` }))} {...field('repair_request_id')} /></Field>
                    </div>
                )}
                <Field label="Subject" required error={e.subject}><Input {...field('subject')} /></Field>
                <Field label="Describe what happened" required error={e.description}><Textarea rows={5} {...field('description')} /></Field>
                <Field label="Evidence (photos, bills — optional)" error={e['attachments.0']}><FileDrop multiple accept="image/*,application/pdf" files={values.attachments} onChange={set('attachments')} label="Attach files" hint="JPG, PNG or PDF up to 5 MB" /></Field>
                <Button size="lg" className="w-full" busy={submit.busy}>Submit complaint</Button>
            </form>
        </div>
    );
}

function Attachment({ a }) {
    const download = async () => {
        let blob;
        try { blob = await fetchPrivate(`/ticket-attachments/${a.id}`); } catch { return; }
        const url = URL.createObjectURL(blob);
        Object.assign(document.createElement('a'), { href: url, download: a.original_name }).click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };
    return <button onClick={download} className="chip"><Icon name="file" className="h-4 w-4" />{a.original_name}</button>;
}

export function TicketThread({ ticket: t, base = '/tickets', onReply, staff }) {
    const [msg, setMsg] = useState('');
    const [internal, setInternal] = useState(false);
    const toast = useToast();
    const reply = useAction(() => post(`${base}/${t.id}/messages`, { message: msg, is_internal: internal }), { onSuccess: () => { setMsg(''); onReply(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <div className="space-y-4">
            <Section title="Conversation">
                <div className="space-y-3">
                    <div className="rounded-2xl bg-slate-50 p-3.5 text-sm"><p className="mb-1 text-xs font-semibold text-slate-500">{t.user?.name} · {dateTime(t.created_at)}</p><p className="whitespace-pre-line text-slate-800">{t.description}</p></div>
                    {t.messages.map((m) => (
                        <div key={m.id} className={`rounded-2xl p-3.5 text-sm ${m.is_internal ? 'border border-dashed border-amber-300 bg-amber-50' : ['admin', 'support'].includes(m.user?.role) ? 'bg-brand-50' : 'bg-slate-50'}`}>
                            <p className="mb-1 text-xs font-semibold text-slate-500">{m.user?.name} ({label(m.user?.role)}) · {dateTime(m.created_at)} {m.is_internal && <Badge tone="amber">Internal note</Badge>}</p>
                            <p className="whitespace-pre-line text-slate-800">{m.message}</p>
                        </div>
                    ))}
                </div>
                {t.attachments?.length > 0 && <div className="mt-4 flex flex-wrap gap-2">{t.attachments.map((a) => <Attachment key={a.id} a={a} />)}</div>}
            </Section>
            {t.status !== 'closed' && (
                <form className="card space-y-3 p-4" onSubmit={(e) => { e.preventDefault(); reply.run(); }}>
                    <Textarea rows={3} placeholder="Write a reply…" value={msg} onChange={(e) => setMsg(e.target.value)} required />
                    <div className="flex items-center justify-between gap-2">
                        {staff ? <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={internal} onChange={(e) => setInternal(e.target.checked)} />Internal note (hidden from customer)</label> : <span />}
                        <Button busy={reply.busy}>Send</Button>
                    </div>
                </form>
            )}
        </div>
    );
}

export function TicketDetail() {
    const { id } = useParams();
    const state = useApi(`/tickets/${id}`);
    return (
        <Async state={state}>
            {({ ticket: t }) => (
                <div className="mx-auto max-w-3xl space-y-4">
                    <PageHeader back="/support" title={t.subject} subtitle={`${t.reference} · ${label(t.category)}`} actions={<StatusBadge status={t.status} />} />
                    {!t.acknowledged_at && <Alert tone="amber" icon="clock">Our team will acknowledge this by {dateTime(t.acknowledge_due_at)}.</Alert>}
                    {t.resolution_notes && <Alert tone="green" icon="check" title="Resolution">{t.resolution_notes}</Alert>}
                    {(t.order || t.repair_request) && <p className="text-sm text-slate-600">Related: {t.order && <Link className="font-semibold text-brand-700" to={`/orders/${t.order.id}`}>{t.order.reference}</Link>} {t.repair_request && <Link className="font-semibold text-brand-700" to={`/repairs/${t.repair_request.id}`}>{t.repair_request.reference}</Link>}</p>}
                    <TicketThread ticket={t} onReply={state.reload} />
                </div>
            )}
        </Async>
    );
}
