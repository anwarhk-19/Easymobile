import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import { Alert, Async, Badge, Button, Checkbox, ConfirmDialog, DL, Empty, Field, FileDrop, Input, Modal, PageHeader, Pagination, Section, Select, StarInput, StatusBadge, Textarea, Timeline, WhatsAppButton } from '../../components/ui';
import { get, post, tokenStore } from '../../lib/api';
import { useAction, useApi, useForm, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { date, dateTime, label, money } from '../../lib/format';

export function MyRepairs() {
    const [page, setPage] = useState(1);
    const state = useApi('/repairs', { page });
    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title="My repairs" back="/account" actions={<Button as={Link} to="/repairs/new"><Icon name="plus" className="h-4 w-4" />New repair</Button>} />
            <Async state={state}>
                {(d) => d.data.length === 0 ? <Empty icon="wrench" title="No repair requests" action={<Button as={Link} to="/repairs">Find a technician</Button>}>Describe your phone’s problem and get a quotation before any work starts.</Empty> : (
                    <><ul className="space-y-3">{d.data.map((r) => (
                        <li key={r.id}><Link to={`/repairs/${r.id}`} className="card flex items-center justify-between gap-3 p-4 transition hover:border-brand-500">
                            <div className="min-w-0"><p className="font-semibold text-slate-900">{r.brand.name} {r.phone_model?.name || r.device_model_text} · {r.repair_category.name}</p><p className="text-sm text-slate-600">{r.technician.name}</p><p className="mt-1 text-xs text-slate-400">{r.reference} · {dateTime(r.created_at)}</p></div>
                            <StatusBadge status={r.status} />
                        </Link></li>
                    ))}</ul><Pagination meta={d} onPage={setPage} /></>
                )}
            </Async>
        </div>
    );
}

export function NewRepair() {
    const meta = useMeta();
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const toast = useToast();
    const { values, field, set } = useForm({ technician_id: params.get('technician') || '', brand_id: '', phone_model_id: '', device_model_text: '', repair_category_id: '', issue_description: '', service_mode: 'walk_in', preferred_area_id: '', address: '', preferred_time: '', data_access_consent: false, imei: '', images: [] });
    const [models, setModels] = useState([]);
    const [techs, setTechs] = useState(null);
    const [shareImei, setShareImei] = useState(false);
    useEffect(() => { if (values.brand_id) get('/phone-models', { brand_id: values.brand_id }).then(setModels); else setModels([]); }, [values.brand_id]);
    const catSlug = meta?.repair_categories?.find((c) => String(c.id) === String(values.repair_category_id))?.slug;
    useEffect(() => { get('/technicians', { repair_category: catSlug, brand_id: values.brand_id }).then((r) => setTechs(r.data)).catch(() => setTechs([])); }, [catSlug, values.brand_id]);
    const tech = techs?.find((t) => String(t.id) === String(values.technician_id));

    const submit = useAction(() => post('/repairs', { ...values, imei: shareImei ? values.imei : undefined, phone_model_id: values.phone_model_id || undefined, preferred_area_id: values.preferred_area_id || undefined }, { form: true }),
        { onSuccess: (r) => { toast(r.message); navigate(`/repairs/${r.repair.id}`); }, onError: (e) => toast(e.message, 'error') });
    const e = submit.errors;

    return (
        <div className="mx-auto max-w-2xl">
            <PageHeader back="/repairs" title="Request a repair" subtitle="The technician reviews your request and sends a quotation. No work starts until you approve it." />
            <form className="space-y-4" onSubmit={(ev) => { ev.preventDefault(); submit.run(); }}>
                <Section title="1. Your device">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Brand" required error={e.brand_id}><Select placeholder="Select brand" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} {...field('brand_id')} /></Field>
                        {models.length > 0 ? (
                            <Field label="Model" error={e.phone_model_id}><Select placeholder="Select model (or type below)" options={models.map((m) => ({ value: m.id, label: m.name }))} {...field('phone_model_id')} /></Field>
                        ) : <div />}
                        {!values.phone_model_id && <Field label="Model name" required={!values.phone_model_id} error={e.device_model_text} className="sm:col-span-2"><Input placeholder="e.g. Galaxy M31" {...field('device_model_text')} /></Field>}
                    </div>
                </Section>
                <Section title="2. The problem">
                    <div className="space-y-4">
                        <Field label="Repair type" required error={e.repair_category_id}><Select placeholder="Select repair type" options={(meta?.repair_categories || []).map((c) => ({ value: c.id, label: c.name }))} {...field('repair_category_id')} /></Field>
                        <Field label="Describe the issue" required error={e.issue_description} hint="When did it start? Any drops or water exposure?"><Textarea rows={4} {...field('issue_description')} /></Field>
                        <Field label="Photos (optional)" error={e.images || e['images.0']}><FileDrop multiple files={values.images} onChange={set('images')} hint="Up to 5 images, max 5 MB each. Visible only to your technician and support." /></Field>
                    </div>
                </Section>
                <Section title="3. Choose a technician">
                    {techs === null ? <p className="text-sm text-slate-500">Loading technicians…</p> : techs.length === 0 ? <p className="text-sm text-slate-500">No technician matches this brand and repair type yet. Try “Other repairs”.</p> : (
                        <div className="grid gap-2">{techs.map((t) => (
                            <label key={t.id} className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 ${String(values.technician_id) === String(t.id) ? 'border-brand-600 bg-brand-50' : 'border-slate-200'}`}>
                                <input type="radio" className="sr-only" name="tech" checked={String(values.technician_id) === String(t.id)} onChange={() => set('technician_id')(t.id)} />
                                <span><span className="block font-semibold text-slate-900">{t.name}</span><span className="text-xs text-slate-500">{t.area?.name} · {t.experience_years}+ yrs · {t.default_warranty_days}-day warranty</span></span>
                                <span className="text-xs text-slate-500">★ {Number(t.rating_avg).toFixed(1)}</span>
                            </label>
                        ))}</div>
                    )}
                    {e.technician_id && <p className="mt-2 text-xs font-medium text-red-600">{e.technician_id[0]}</p>}
                </Section>
                <Section title="4. Service mode">
                    <div className="space-y-4">
                        <div className="grid grid-cols-3 gap-2">{[['walk_in', 'Walk-in', true], ['pickup_drop', 'Pickup & drop', tech?.pickup_available], ['onsite', 'On-site', tech?.onsite_available]].map(([v, t, ok]) => (
                            <button key={v} type="button" disabled={tech && !ok} onClick={() => set('service_mode')(v)} className={`rounded-xl border px-2 py-2.5 text-sm font-semibold disabled:opacity-40 ${values.service_mode === v ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-300 text-slate-700'}`}>{t}</button>
                        ))}</div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Preferred area"><Select placeholder="Select area" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} {...field('preferred_area_id')} /></Field>
                            <Field label="Preferred time"><Input placeholder="e.g. Tomorrow after 4 PM" {...field('preferred_time')} /></Field>
                        </div>
                        {values.service_mode !== 'walk_in' && <Field label="Pickup / visit address" required error={e.address}><Textarea rows={2} {...field('address')} /></Field>}
                    </div>
                </Section>
                <Section title="Privacy">
                    <div className="space-y-3">
                        <Alert tone="blue" icon="shield">Back up your data and remove screen locks only if the technician asks. Technicians may not access your photos, messages or accounts.</Alert>
                        <Checkbox label="I allow the technician to unlock and test the device only as needed for this repair." checked={values.data_access_consent} onChange={set('data_access_consent')} />
                        <Checkbox label="Share my IMEI (only needed for network, warranty or lock issues)" checked={shareImei} onChange={(ev) => setShareImei(ev.target.checked)} />
                        {shareImei && <Field label="IMEI" error={e.imei} hint="Dial *#06# to see it. Stored encrypted; visible only to your technician after acceptance."><Input inputMode="numeric" maxLength={16} {...field('imei')} /></Field>}
                    </div>
                </Section>
                <Button size="lg" className="w-full" busy={submit.busy}>Send repair request</Button>
            </form>
        </div>
    );
}

function Quotation({ q, highlight }) {
    return (
        <div className={`rounded-2xl border p-4 ${highlight ? 'border-brand-500 bg-brand-50/40' : 'border-slate-200'}`}>
            <div className="mb-2 flex items-center justify-between"><p className="font-bold text-slate-900">Quotation v{q.version}</p><StatusBadge status={q.status} /></div>
            {q.change_reason && <p className="mb-2 text-sm text-amber-800"><b>Why it changed:</b> {q.change_reason}</p>}
            <p className="text-sm text-slate-700">{q.diagnosis}</p>
            <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div><dt className="text-slate-500">Parts</dt><dd className="font-medium">{money(q.parts_cost)} · {label(q.parts_type)}</dd></div>
                <div><dt className="text-slate-500">Labour</dt><dd className="font-medium">{money(q.labour_cost)}</dd></div>
                <div><dt className="text-slate-500">Estimated time</dt><dd className="font-medium">{q.estimated_hours} hours</dd></div>
                <div><dt className="text-slate-500">Warranty</dt><dd className="font-medium">{q.warranty_days ? `${q.warranty_days} days` : 'None'}</dd></div>
            </dl>
            {q.warranty_exclusions && <p className="mt-2 text-xs text-slate-500">Exclusions: {q.warranty_exclusions}</p>}
            <p className="mt-3 border-t border-slate-200 pt-2 text-right text-lg font-extrabold text-slate-900">Total {money(q.total)}</p>
        </div>
    );
}

export function RepairDetail() {
    const { id } = useParams();
    const toast = useToast();
    const state = useApi(`/repairs/${id}`);
    const [dialog, setDialog] = useState(null);
    const [rating, setRating] = useState(5);
    const [comment, setComment] = useState('');
    const act = useAction((path, body) => post(`/repairs/${id}/${path}`, body), { onSuccess: () => { setDialog(null); state.reload(); toast('Updated'); }, onError: (e) => toast(e.message, 'error') });

    return (
        <Async state={state}>
            {({ repair: r, can_review, whatsapp_link, imei }) => {
                const pending = r.quotations.find((q) => q.status === 'pending');
                return (
                    <div className="mx-auto max-w-3xl space-y-4">
                        <PageHeader back="/my-repairs" title={`${r.brand.name} ${r.phone_model?.name || r.device_model_text}`} subtitle={`${r.repair_category.name} · ${r.reference}`} actions={<StatusBadge status={r.status} />} />

                        {r.status === 'submitted' && <Alert tone="amber" icon="clock" title="Waiting for the technician">{r.technician.name} will accept your request and diagnose the device.</Alert>}
                        {pending && (
                            <Alert tone="blue" icon="list" title={r.started_at ? 'The quotation has changed — work is paused' : 'Your quotation is ready'}>
                                Repair starts only after you approve.
                                <div className="mt-3"><Quotation q={pending} highlight /></div>
                                <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => setDialog('approve')}>Approve {money(pending.total)}</Button><Button size="sm" variant="secondary" onClick={() => setDialog('reject')}>Reject</Button></div>
                            </Alert>
                        )}
                        {r.status === 'ready_for_handover' && !r.handover_confirmed_at && (
                            <Alert tone="green" icon="check" title="Your device is ready">At handover, check the device works as expected (display, touch, charging, camera, calls) before confirming.
                                <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => setDialog('handover')}>I checked my device</Button><Button as={Link} size="sm" variant="secondary" to={`/support/new?repair=${r.id}`}>Report a problem</Button></div></Alert>
                        )}
                        {r.warranty && (
                            <Section title="Final bill & warranty" action={<Badge tone={r.warranty.is_active ? 'green' : 'slate'}>{r.warranty.is_active ? 'Warranty active' : 'Warranty expired'}</Badge>}>
                                <DL items={[['Final amount', money(r.final_amount)], ['Payment', r.payment_status === 'paid' ? `Paid (${label(r.payment_method)})` : 'Pending'], ['Warranty code', <span key="w" className="font-mono">{r.warranty.warranty_code}</span>], ['Valid', `${date(r.warranty.starts_at)} – ${date(r.warranty.ends_at)}`], ['Terms', r.warranty.terms], ['Exclusions', r.warranty.exclusions]]} />
                                <Button as={Link} to={`/support/new?repair=${r.id}&category=warranty_issue`} variant="secondary" size="sm" className="mt-4">Make a warranty claim</Button>
                            </Section>
                        )}
                        {r.status === 'completed' && !r.warranty && <Section title="Final bill"><DL items={[['Final amount', money(r.final_amount)], ['Warranty', 'No warranty on this repair']]} /></Section>}
                        {can_review && <Alert tone="green" icon="star" title="Rate your repair"><div className="mt-2"><Button size="sm" onClick={() => setDialog('review')}>Leave feedback</Button></div></Alert>}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Section title="Request details">
                                <DL items={[['Issue', r.issue_description], ['Service', label(r.service_mode)], r.address && ['Address', r.address], r.preferred_time && ['Preferred time', r.preferred_time], ['Device access consent', r.data_access_consent ? 'Given' : 'Not given'], imei && ['IMEI (private)', imei]]} />
                                {r.images.length > 0 && <div className="mt-3 flex gap-2">{r.images.map((im) => <AuthImage key={im.id} url={im.url} />)}</div>}
                            </Section>
                            <Section title="Technician">
                                <p className="font-semibold text-slate-900">{r.technician.name}</p><p className="text-sm text-slate-600">{r.technician.address}</p>
                                <div className="mt-3 flex flex-wrap gap-2"><WhatsAppButton href={whatsapp_link} size="sm" /><Button as="a" href={`tel:+91${r.technician.phone}`} size="sm" variant="secondary"><Icon name="phone" className="h-4 w-4" />Call</Button></div>
                            </Section>
                        </div>
                        {r.quotations.filter((q) => q.status !== 'pending').length > 0 && <Section title="Quotation history"><div className="space-y-3">{r.quotations.filter((q) => q.status !== 'pending').reverse().map((q) => <Quotation key={q.id} q={q} highlight={q.status === 'approved'} />)}</div></Section>}
                        {r.parts.length > 0 && <Section title="Parts used"><ul className="divide-y divide-slate-100 text-sm">{r.parts.map((p) => <li key={p.id} className="flex justify-between py-2"><span>{p.name} ×{p.quantity} <Badge>{label(p.parts_type)}</Badge></span><span>{money(p.unit_cost * p.quantity)}</span></li>)}</ul></Section>}
                        {r.test_checklist && <Section title="Functional test"><div className="flex flex-wrap gap-2">{Object.entries(r.test_checklist).map(([k, v]) => <Badge key={k} tone={v ? 'green' : 'red'}>{v ? '✓' : '✗'} {label(k)}</Badge>)}</div></Section>}
                        <Section title="Status timeline"><Timeline items={r.histories} /></Section>
                        {['submitted', 'accepted', 'diagnosing', 'quoted', 'quote_rejected'].includes(r.status) && <div className="text-center"><Button variant="ghost" onClick={() => setDialog('cancel')}>Cancel repair request</Button></div>}

                        <ConfirmDialog open={dialog === 'approve'} onClose={() => setDialog(null)} busy={act.busy} title="Approve quotation" message={`You authorise ${r.technician.name} to carry out this repair for ${money(pending?.total)}. If the price changes, you'll be asked again.`} confirmText="Approve" onConfirm={() => act.run('quotation', { decision: 'approve' })} />
                        <ConfirmDialog open={dialog === 'reject'} onClose={() => setDialog(null)} busy={act.busy} title="Reject quotation" reason reasonLabel="Tell the technician why (optional but helpful)" tone="danger" confirmText="Reject" onConfirm={(note) => act.run('quotation', { decision: 'reject', note })} />
                        <ConfirmDialog open={dialog === 'handover'} onClose={() => setDialog(null)} busy={act.busy} title="Confirm device check" message="Confirm that you have checked the device at handover and it works as expected." confirmText="Confirm" onConfirm={() => act.run('handover')} />
                        <ConfirmDialog open={dialog === 'cancel'} onClose={() => setDialog(null)} busy={act.busy} title="Cancel repair" reason tone="danger" confirmText="Cancel repair" onConfirm={(reason) => act.run('cancel', { reason })} />
                        <Modal open={dialog === 'review'} onClose={() => setDialog(null)} title="Rate this repair" footer={<Button busy={act.busy} onClick={() => act.run('review', { rating, comment })}>Submit</Button>}>
                            <div className="space-y-4"><StarInput value={rating} onChange={setRating} /><Textarea placeholder="How was the repair quality and service?" value={comment} onChange={(ev) => setComment(ev.target.value)} /></div>
                        </Modal>
                    </div>
                );
            }}
        </Async>
    );
}

/** Repair photos are private: fetched with the auth token and shown as blob URLs. */
export function AuthImage({ url }) {
    const [src, setSrc] = useState(null);
    useEffect(() => {
        let u;
        const token = tokenStore.get();
        fetch(url, { headers: { Authorization: `Bearer ${token}` } }).then((r) => (r.ok ? r.blob() : null)).then((b) => b && setSrc((u = URL.createObjectURL(b)))).catch(() => {});
        return () => u && URL.revokeObjectURL(u);
    }, [url]);
    return src ? <a href={src} target="_blank" rel="noreferrer"><img src={src} alt="Repair photo" className="h-20 w-20 rounded-xl object-cover" /></a> : <div className="h-20 w-20 animate-pulse rounded-xl bg-slate-200" />;
}
