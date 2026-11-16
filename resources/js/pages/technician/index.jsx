import { useEffect, useState } from 'react';
import { Link, Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom';
import Icon from '../../components/icons';
import DataTable from '../../components/DataTable';
import { Alert, Async, Badge, Button, Checkbox, ConfirmDialog, DL, Field, Input, Modal, PageHeader, Section, Select, Stat, StatusBadge, Tabs, Textarea, Timeline, WhatsAppButton } from '../../components/ui';
import NotFound from '../public/NotFound';
import { DaysPicker, DocumentsInput, openPrivate } from '../merchant/ShopForm';
import { DateRange, defaultRange } from '../merchant/Other';
import { AuthImage } from '../customer/Repairs';
import { FileDrop } from '../../components/ui';
import { get, post } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction, useApi, useForm, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { ago, dateTime, label, minutes, money } from '../../lib/format';

export default function TechnicianRoutes() {
    return (
        <Routes>
            <Route index element={<Dashboard />} />
            <Route path="profile" element={<Profile />} />
            <Route path="repairs" element={<RepairList />} />
            <Route path="repairs/:id" element={<RepairJob />} />
            <Route path="reports" element={<Reports />} />
            <Route path="*" element={<NotFound />} />
        </Routes>
    );
}

const device = (r) => `${r.brand?.name || ''} ${r.phone_model?.name || r.device_model_text || ''}`.trim();

function Dashboard() {
    const state = useApi('/technician/dashboard');
    if (state.error?.data?.code === 'profile_required') return <Navigate to="/technician/profile" replace />;
    return (
        <Async state={state}>
            {({ profile, stats: s, recent }) => (
                <div className="space-y-5">
                    <PageHeader title={profile.name} subtitle="Technician dashboard" actions={profile.status === 'approved' && <Button as={Link} variant="secondary" to={`/technicians/${profile.slug}`}>View public profile</Button>} />
                    {profile.status === 'pending' && <Alert tone="amber" icon="clock" title="Profile under review">Customers can find you once our team approves your profile.</Alert>}
                    {['rejected', 'suspended'].includes(profile.status) && <Alert tone="red" title={`Profile ${profile.status}`}>{profile.rejection_reason}</Alert>}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Stat label="New requests" value={s.new_requests} icon="bell" tone="amber" to="/technician/repairs?status=new" />
                        <Stat label="Need quotation" value={s.awaiting_quote} icon="list" tone="blue" to="/technician/repairs" />
                        <Stat label="Awaiting approval" value={s.awaiting_approval} icon="clock" tone="slate" />
                        <Stat label="In progress" value={s.in_progress} icon="wrench" tone="brand" />
                        <Stat label="Ready for handover" value={s.ready_for_handover} icon="check" tone="green" />
                        <Stat label="Completed (month)" value={s.completed_this_month} icon="check" tone="green" />
                        <Stat label="Revenue (month)" value={money(s.revenue_this_month)} icon="rupee" />
                        <Stat label="Rating" value={s.rating_count ? `${Number(s.rating_avg).toFixed(1)} ★` : '—'} hint={`${s.rating_count} reviews`} icon="star" tone="amber" />
                    </div>
                    <Section title="Open jobs" action={<Link className="text-sm font-semibold text-brand-700" to="/technician/repairs">All jobs</Link>}>
                        {recent.length === 0 ? <p className="text-sm text-slate-500">No open jobs right now.</p> : (
                            <ul className="divide-y divide-slate-100">{recent.map((r) => (
                                <li key={r.id}><Link to={`/technician/repairs/${r.id}`} className="flex items-center justify-between gap-3 py-2.5 text-sm hover:text-brand-700"><span><b>{device(r)}</b> · {r.repair_category.name}<span className="block text-xs text-slate-400">{r.reference} · {r.customer?.name} · {ago(r.created_at)}</span></span><StatusBadge status={r.status} /></Link></li>
                            ))}</ul>
                        )}
                    </Section>
                </div>
            )}
        </Async>
    );
}

function RepairList() {
    const navigate = useNavigate();
    const [status, setStatus] = useState(new URLSearchParams(window.location.search).get('status') || 'active');
    const [page, setPage] = useState(1);
    const state = useApi('/technician/repairs', { status, page });
    return (
        <div>
            <PageHeader title="Repair jobs" />
            <Tabs className="mb-4" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['new', 'New'], ['active', 'Active'], ['closed', 'Closed'], ['', 'All']]} />
            <DataTable state={state} onPage={setPage} empty="No jobs here" emptyIcon="wrench" onRowClick={(r) => navigate(`/technician/repairs/${r.id}`)} columns={[
                { key: 'reference', label: 'Job', render: (r) => <><b>{r.reference}</b><span className="block text-xs text-slate-400">{ago(r.created_at)}</span></> },
                { key: 'device', label: 'Device', render: (r) => device(r) }, { key: 'cat', label: 'Repair', render: (r) => r.repair_category.name },
                { key: 'mode', label: 'Mode', render: (r) => label(r.service_mode) }, { key: 'customer', label: 'Customer', render: (r) => r.customer?.name },
                { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
            ]} />
        </div>
    );
}

const CHECKS = ['display', 'touch', 'charging', 'battery', 'speaker', 'microphone', 'camera', 'network', 'wifi', 'buttons'];

function QuoteModal({ repair, revision, onClose, onDone }) {
    const toast = useToast();
    const last = repair.quotations[repair.quotations.length - 1];
    const { values, field } = useForm({ diagnosis: last?.diagnosis || repair.diagnoses?.[repair.diagnoses.length - 1]?.findings || '', parts_cost: last?.parts_cost || '', labour_cost: last?.labour_cost || '', parts_type: last?.parts_type || 'original', estimated_hours: last?.estimated_hours || 2, warranty_days: last?.warranty_days ?? 30, warranty_exclusions: last?.warranty_exclusions || 'Physical or liquid damage after handover.', change_reason: '' });
    const act = useAction(() => post(`/technician/repairs/${repair.id}/quotation`, values), { onSuccess: () => { toast('Quotation sent to customer'); onDone(); }, onError: (e) => toast(e.message, 'error') });
    const e = act.errors;
    const total = Number(values.parts_cost || 0) + Number(values.labour_cost || 0);
    return (
        <Modal open onClose={onClose} size="max-w-xl" title={revision ? 'Revise quotation' : 'Submit quotation'} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button busy={act.busy} onClick={() => act.run()}>Send · {money(total)}</Button></>}>
            <div className="space-y-3">
                {revision && <Alert tone="amber" title="Work will pause until the customer approves the revised quotation." />}
                <Field label="Diagnosis" required error={e.diagnosis}><Textarea rows={3} {...field('diagnosis')} /></Field>
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Parts cost ₹" required error={e.parts_cost}><Input type="number" min="0" {...field('parts_cost')} /></Field>
                    <Field label="Labour cost ₹" required error={e.labour_cost}><Input type="number" min="0" {...field('labour_cost')} /></Field>
                    <Field label="Parts type" required error={e.parts_type}><Select options={[{ value: 'original', label: 'Original' }, { value: 'oem_compatible', label: 'OEM-compatible' }, { value: 'refurbished', label: 'Refurbished' }, { value: 'aftermarket', label: 'Aftermarket' }]} {...field('parts_type')} /></Field>
                    <Field label="Estimated time (hours)" required error={e.estimated_hours}><Input type="number" min="1" {...field('estimated_hours')} /></Field>
                    <Field label="Warranty (days)" required error={e.warranty_days}><Input type="number" min="0" {...field('warranty_days')} /></Field>
                </div>
                <Field label="Warranty exclusions"><Textarea rows={2} {...field('warranty_exclusions')} /></Field>
                {revision && <Field label="Why has the quotation changed?" required error={e.change_reason}><Textarea rows={2} {...field('change_reason')} /></Field>}
            </div>
        </Modal>
    );
}

function RepairJob() {
    const { id } = useParams();
    const toast = useToast();
    const state = useApi(`/technician/repairs/${id}`);
    const [dialog, setDialog] = useState(null);
    const [text, setText] = useState('');
    const [checks, setChecks] = useState(Object.fromEntries(CHECKS.map((c) => [c, true])));
    const [part, setPart] = useState({ name: '', parts_type: 'original', quantity: 1, unit_cost: '' });
    const [completion, setCompletion] = useState({ customer_verified_in_person: false, payment_method: '' });
    const act = useAction((path, body) => post(`/technician/repairs/${id}/${path}`, body), { onSuccess: () => { setDialog(null); setText(''); state.reload(); toast('Job updated'); }, onError: (e) => toast(e.message, 'error') });

    return (
        <Async state={state}>
            {({ repair: r, imei, whatsapp_customer_link }) => {
                const s = r.status;
                const pending = r.quotations.find((q) => q.status === 'pending');
                const approved = r.quotations.find((q) => q.status === 'approved');
                return (
                    <div className="space-y-4">
                        <PageHeader back="/technician/repairs" title={`${device(r)} · ${r.repair_category.name}`} subtitle={`${r.reference} · received ${dateTime(r.created_at)}`} actions={<StatusBadge status={s} />} />
                        <div className="flex flex-wrap gap-2">
                            {s === 'submitted' && <><Button onClick={() => act.run('accept')} busy={act.busy}>Accept job</Button><Button variant="secondary" onClick={() => setDialog('reject')}>Decline</Button></>}
                            {['accepted', 'diagnosing', 'quote_rejected', 'in_progress'].includes(s) && <Button variant={s === 'in_progress' ? 'secondary' : 'primary'} onClick={() => setDialog('diagnosis')}>Add diagnosis</Button>}
                            {['accepted', 'diagnosing', 'quote_rejected', 'quoted'].includes(s) && <Button onClick={() => setDialog('quote')}>{pending ? 'Replace quotation' : 'Send quotation'}</Button>}
                            {s === 'approved' && <Button onClick={() => act.run('start')} busy={act.busy}>Start repair</Button>}
                            {['in_progress', 'testing', 'approved'].includes(s) && <Button variant="secondary" onClick={() => setDialog('revise')}>Revise quotation</Button>}
                            {['approved', 'in_progress', 'testing'].includes(s) && <Button variant="secondary" onClick={() => setDialog('part')}>Record part</Button>}
                            {['in_progress', 'testing'].includes(s) && <Button variant="secondary" onClick={() => setDialog('progress')}>Progress note</Button>}
                            {s === 'in_progress' && <Button onClick={() => act.run('testing')} busy={act.busy}>Start functional test</Button>}
                            {s === 'testing' && <Button onClick={() => setDialog('test')}>Record test result</Button>}
                            {s === 'ready_for_handover' && <Button onClick={() => setDialog('complete')}>Complete & hand over</Button>}
                            <WhatsAppButton href={whatsapp_customer_link}>Customer</WhatsAppButton>
                            {['submitted', 'accepted', 'diagnosing', 'quoted', 'quote_rejected'].includes(s) && <Button variant="ghost" onClick={() => setDialog('cancel')}>Cancel job</Button>}
                        </div>
                        {s === 'quoted' && <Alert tone="blue" icon="clock" title="Waiting for customer approval">Do not start or continue work until the customer approves.</Alert>}
                        {s === 'quote_rejected' && <Alert tone="amber" title="Customer rejected the quotation">{r.quotations[r.quotations.length - 1]?.customer_note || 'Send a revised quotation or cancel the job and return the device.'}</Alert>}

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Section title="Customer request">
                                <DL items={[['Customer', r.customer.name], ['Phone', r.customer.phone ? <a key="p" className="text-brand-700" href={`tel:+91${r.customer.phone}`}>+91 {r.customer.phone}</a> : 'Shared after you accept'], ['Issue', r.issue_description], ['Service mode', label(r.service_mode)],
                                    r.address && ['Address', r.address], r.preferred_area && ['Area', r.preferred_area.name], r.preferred_time && ['Preferred time', r.preferred_time], ['Device access consent', r.data_access_consent ? 'Given — access only what this repair needs' : 'Not given'], imei && ['IMEI', imei]]} />
                                {r.images.length > 0 && <div className="mt-3 flex gap-2">{r.images.map((im) => <AuthImage key={im.id} url={im.url} />)}</div>}
                            </Section>
                            <Section title="Quotations">
                                {r.quotations.length === 0 ? <p className="text-sm text-slate-500">No quotation yet.</p> : (
                                    <ul className="space-y-2">{[...r.quotations].reverse().map((q) => (
                                        <li key={q.id} className="rounded-xl border border-slate-200 p-3 text-sm"><div className="flex justify-between"><b>v{q.version} · {money(q.total)}</b><StatusBadge status={q.status} /></div><p className="text-slate-600">{label(q.parts_type)} · {q.estimated_hours}h · {q.warranty_days}d warranty</p>{q.change_reason && <p className="text-xs text-amber-700">Change: {q.change_reason}</p>}</li>
                                    ))}</ul>
                                )}
                                {approved && <p className="mt-3 text-sm">Approved amount: <b>{money(approved.total)}</b></p>}
                            </Section>
                        </div>
                        {r.diagnoses.length > 0 && <Section title="Diagnosis notes"><ul className="space-y-2 text-sm">{r.diagnoses.map((d) => <li key={d.id}>• {d.findings} <span className="text-xs text-slate-400">{dateTime(d.created_at)}</span></li>)}</ul></Section>}
                        {r.parts.length > 0 && <Section title="Parts used"><ul className="divide-y divide-slate-100 text-sm">{r.parts.map((p) => <li key={p.id} className="flex justify-between py-2"><span>{p.name} ×{p.quantity} <Badge>{label(p.parts_type)}</Badge></span><span>{money(p.unit_cost * p.quantity)}</span></li>)}</ul></Section>}
                        {r.warranty && <Section title="Warranty issued"><DL items={[['Code', r.warranty.warranty_code], ['Valid until', r.warranty.ends_at?.slice(0, 10)], ['Final bill', money(r.final_amount)]]} /></Section>}
                        <Section title="Timeline"><Timeline items={r.histories} /></Section>

                        {(dialog === 'quote' || dialog === 'revise') && <QuoteModal repair={r} revision={dialog === 'revise'} onClose={() => setDialog(null)} onDone={() => { setDialog(null); state.reload(); }} />}
                        <ConfirmDialog open={dialog === 'reject'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason title="Decline request" confirmText="Decline" onConfirm={(reason) => act.run('reject', { reason })} />
                        <ConfirmDialog open={dialog === 'cancel'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason title="Cancel job" confirmText="Cancel job" onConfirm={(reason) => act.run('cancel', { reason })} />
                        <Modal open={dialog === 'diagnosis'} onClose={() => setDialog(null)} title="Diagnosis findings" footer={<Button busy={act.busy} onClick={() => act.run('diagnosis', { findings: text })}>Save</Button>}><Textarea rows={4} value={text} onChange={(e) => setText(e.target.value)} placeholder="What did you find?" /></Modal>
                        <Modal open={dialog === 'progress'} onClose={() => setDialog(null)} title="Progress note" footer={<Button busy={act.busy} onClick={() => act.run('progress', { note: text })}>Add note</Button>}><Textarea rows={3} value={text} onChange={(e) => setText(e.target.value)} placeholder="Shared with the customer" /></Modal>
                        <Modal open={dialog === 'part'} onClose={() => setDialog(null)} title="Record part used" footer={<Button busy={act.busy} onClick={() => act.run('part', part)}>Save part</Button>}>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Part name" className="col-span-2"><Input value={part.name} onChange={(e) => setPart({ ...part, name: e.target.value })} /></Field>
                                <Field label="Type"><Select value={part.parts_type} onChange={(e) => setPart({ ...part, parts_type: e.target.value })} options={['original', 'oem_compatible', 'refurbished', 'aftermarket']} /></Field>
                                <Field label="Qty"><Input type="number" min="1" value={part.quantity} onChange={(e) => setPart({ ...part, quantity: e.target.value })} /></Field>
                                <Field label="Unit cost ₹"><Input type="number" min="0" value={part.unit_cost} onChange={(e) => setPart({ ...part, unit_cost: e.target.value })} /></Field>
                            </div>
                        </Modal>
                        <Modal open={dialog === 'test'} onClose={() => setDialog(null)} title="Functional test" footer={<><Button variant="secondary" busy={act.busy} onClick={() => act.run('test-result', { checklist: checks, passed: false, notes: text })}>Failed — back to repair</Button><Button busy={act.busy} disabled={!Object.values(checks).every(Boolean)} onClick={() => act.run('test-result', { checklist: checks, passed: true, notes: text })}>All passed</Button></>}>
                            <div className="grid grid-cols-2 gap-2">{CHECKS.map((c) => <Checkbox key={c} label={label(c)} checked={checks[c]} onChange={(e) => setChecks({ ...checks, [c]: e.target.checked })} />)}</div>
                            <Textarea className="mt-3" rows={2} value={text} onChange={(e) => setText(e.target.value)} placeholder="Notes for the customer" />
                        </Modal>
                        <Modal open={dialog === 'complete'} onClose={() => setDialog(null)} title="Complete repair" footer={<Button busy={act.busy} onClick={() => act.run('complete', { ...completion, payment_method: completion.payment_method || undefined })}>Complete job</Button>}>
                            <div className="space-y-3">
                                {r.handover_confirmed_at ? <Alert tone="green" icon="check">Customer confirmed the device check at {dateTime(r.handover_confirmed_at)}.</Alert> : <Alert tone="amber">The customer hasn’t confirmed the handover check in the app.</Alert>}
                                {!r.handover_confirmed_at && <Checkbox label="The customer checked the device in person at handover" checked={completion.customer_verified_in_person} onChange={(e) => setCompletion({ ...completion, customer_verified_in_person: e.target.checked })} />}
                                <Field label="Payment received"><Select value={completion.payment_method} onChange={(e) => setCompletion({ ...completion, payment_method: e.target.value })} placeholder="Not yet paid" options={['cash', 'upi', 'card']} /></Field>
                                <p className="text-sm text-slate-600">Final bill: <b>{money(approved?.total)}</b>. A warranty card is issued automatically.</p>
                            </div>
                        </Modal>
                    </div>
                );
            }}
        </Async>
    );
}

function Reports() {
    const [range, setRange] = useState(defaultRange);
    const state = useApi('/technician/reports', range);
    return (
        <div>
            <PageHeader title="Reports" actions={<DateRange value={range} onChange={setRange} />} />
            <Async state={state}>{(r) => (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Stat label="Requests received" value={r.requests_received} icon="bell" />
                    <Stat label="Avg response" value={minutes(r.response_minutes)} icon="clock" tone="blue" />
                    <Stat label="Quotes submitted" value={r.quotes_submitted} icon="list" tone="blue" />
                    <Stat label="Quotes accepted" value={r.quotes_accepted} hint={`${r.quotes_rejected} rejected`} icon="check" tone="green" />
                    <Stat label="Completed repairs" value={r.completed_repairs} icon="wrench" tone="green" />
                    <Stat label="Avg turnaround" value={r.avg_turnaround_hours ? `${r.avg_turnaround_hours} h` : '—'} icon="clock" />
                    <Stat label="Revenue" value={money(r.revenue)} icon="rupee" />
                    <Stat label="Warranty claims" value={r.warranty_claims} icon="shield" tone="red" />
                </div>
            )}</Async>
        </div>
    );
}

function Profile() {
    const state = useApi('/technician/profile');
    return <Async state={state}>{(d) => <ProfileForm profile={d.profile} reload={state.reload} />}</Async>;
}

function ProfileForm({ profile, reload }) {
    const meta = useMeta();
    const toast = useToast();
    const navigate = useNavigate();
    const { user, refresh } = useAuth();
    const [models, setModels] = useState([]);
    const p = profile;
    const { values, field, set } = useForm({
        name: p?.name || user.name, shop_name: p?.shop_name || '', phone: p?.phone || user.phone, whatsapp: p?.whatsapp || user.phone, address: p?.address || '', area_id: p?.area_id || '',
        service_area_ids: p?.service_areas?.map((a) => a.id) || [], brand_ids: p?.brands?.map((b) => b.id) || [], phone_model_ids: p?.phone_models?.map((m) => m.id) || [],
        supported_models_note: p?.supported_models_note || '', repair_category_ids: p?.repair_categories?.map((c) => c.id) || [], experience_years: p?.experience_years ?? 1,
        certifications: p?.certifications || '', opens_at: p?.opens_at?.slice(0, 5) || '10:00', closes_at: p?.closes_at?.slice(0, 5) || '20:00', open_days: p?.open_days || ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
        pickup_available: !!p?.pickup_available, onsite_available: !!p?.onsite_available, default_warranty_days: p?.default_warranty_days ?? 30, warranty_terms: p?.warranty_terms || '', bio: p?.bio || '',
        photo: null, documents: [], accept_agreement: false,
    });
    useEffect(() => { if (values.brand_ids.length) Promise.all(values.brand_ids.map((b) => get('/phone-models', { brand_id: b }))).then((r) => setModels(r.flat())); else setModels([]); }, [values.brand_ids.join(',')]); // eslint-disable-line react-hooks/exhaustive-deps
    const toggle = (k, id) => set(k)(values[k].includes(id) ? values[k].filter((x) => x !== id) : [...values[k], id]);
    const save = useAction(() => post(p ? '/technician/profile/update' : '/technician/profile', values, { form: true }), { onSuccess: (r) => { toast(r.message || 'Profile saved'); refresh(); p ? reload() : navigate('/technician'); }, onError: (e) => toast(e.message, 'error') });
    const e = save.errors;
    const Chips = ({ k, items }) => <div className="flex flex-wrap gap-1.5">{items.map((i) => <button type="button" key={i.id} onClick={() => toggle(k, i.id)} className={`chip !py-1 text-xs ${values[k].includes(i.id) ? 'chip-active' : ''}`}>{i.name}</button>)}</div>;

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title={p ? 'Technician profile' : 'Create your technician profile'} subtitle={!p && 'Our team reviews every profile before it is published.'} actions={p && <StatusBadge status={p.status} />} />
            <form className="space-y-4" onSubmit={(ev) => { ev.preventDefault(); save.run(); }}>
                <Section title="About you">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Display name" required error={e.name}><Input {...field('name')} /></Field>
                        <Field label="Shop name (optional)"><Input {...field('shop_name')} /></Field>
                        <Field label="Contact number" required error={e.phone}><Input type="tel" {...field('phone')} /></Field>
                        <Field label="WhatsApp" error={e.whatsapp}><Input type="tel" {...field('whatsapp')} /></Field>
                        <Field label="Address" required error={e.address} className="sm:col-span-2"><Textarea rows={2} {...field('address')} /></Field>
                        <Field label="Base area" required error={e.area_id}><Select placeholder="Select" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} {...field('area_id')} /></Field>
                        <Field label="Experience (years)" required error={e.experience_years}><Input type="number" min="0" {...field('experience_years')} /></Field>
                        <Field label="Training / certifications" className="sm:col-span-2"><Textarea rows={2} {...field('certifications')} /></Field>
                        <Field label="Short bio" className="sm:col-span-2"><Textarea rows={2} {...field('bio')} /></Field>
                        <Field label="Profile photo"><FileDrop files={values.photo} onChange={set('photo')} label="Upload photo" /></Field>
                    </div>
                </Section>
                <Section title="Services">
                    <div className="space-y-4">
                        <Field label="Repair categories" required error={e.repair_category_ids}><Chips k="repair_category_ids" items={meta?.repair_categories || []} /></Field>
                        <Field label="Supported brands" required error={e.brand_ids}><Chips k="brand_ids" items={meta?.brands || []} /></Field>
                        {models.length > 0 && <Field label="Specific models (optional)"><Chips k="phone_model_ids" items={models} /></Field>}
                        <Field label="Other supported models"><Input {...field('supported_models_note')} placeholder="e.g. Most Samsung J/M series, older iPhones" /></Field>
                        <Field label="Service areas" required error={e.service_area_ids}><Chips k="service_area_ids" items={meta?.areas || []} /></Field>
                        <div className="flex flex-wrap gap-4"><Checkbox label="Pickup & drop-off" checked={values.pickup_available} onChange={set('pickup_available')} /><Checkbox label="On-site visits" checked={values.onsite_available} onChange={set('onsite_available')} /></div>
                    </div>
                </Section>
                <Section title="Hours & warranty">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Opens" error={e.opens_at}><Input type="time" {...field('opens_at')} /></Field>
                        <Field label="Closes" error={e.closes_at}><Input type="time" {...field('closes_at')} /></Field>
                        <Field label="Working days" className="sm:col-span-2"><DaysPicker value={values.open_days} onChange={set('open_days')} /></Field>
                        <Field label="Default warranty (days)" required error={e.default_warranty_days}><Input type="number" min="0" {...field('default_warranty_days')} /></Field>
                        <Field label="Warranty terms" required error={e.warranty_terms} className="sm:col-span-2"><Textarea rows={3} {...field('warranty_terms')} /></Field>
                    </div>
                </Section>
                <Section title="Verification documents">
                    {p?.documents?.length > 0 && <ul className="mb-3 flex flex-wrap gap-2">{p.documents.map((d) => <li key={d.id}><button type="button" className="chip" onClick={() => openPrivate(`/technician-documents/${d.id}`)}>{label(d.type)} · {d.original_name}</button></li>)}</ul>}
                    <DocumentsInput docs={values.documents} onChange={set('documents')} types={['id_proof', 'certificate', 'shop_licence', 'address_proof', 'other']} />
                    {!p && <Checkbox className="mt-4" label="I agree to the technician agreement: transparent quotations, no work without approval, and no access to customer data beyond what the repair requires." checked={values.accept_agreement} onChange={set('accept_agreement')} />}
                    {e.accept_agreement && <p className="mt-1 text-xs text-red-600">{e.accept_agreement[0]}</p>}
                </Section>
                <Button size="lg" busy={save.busy}>{p ? 'Save profile' : 'Submit for review'}</Button>
            </form>
        </div>
    );
}
