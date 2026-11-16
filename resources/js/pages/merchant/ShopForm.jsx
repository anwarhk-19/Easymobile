import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Alert, Async, Badge, Button, Checkbox, Field, FileDrop, Input, PageHeader, Section, Select, StatusBadge, Textarea } from '../../components/ui';
import { del, fetchPrivate, post } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction, useApi, useForm, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { label } from '../../lib/format';

const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
const DOC_TYPES = ['trade_licence', 'gst_certificate', 'shop_establishment', 'id_proof', 'address_proof', 'other'];

export function DaysPicker({ value = [], onChange }) {
    return (
        <div className="flex flex-wrap gap-1.5">{DAYS.map((d) => (
            <button key={d} type="button" onClick={() => onChange(value.includes(d) ? value.filter((x) => x !== d) : [...value, d])} className={`chip !px-3 ${value.includes(d) ? 'chip-active' : ''}`}>{d[0].toUpperCase() + d.slice(1)}</button>
        ))}</div>
    );
}

export function DocumentsInput({ docs, onChange, types = DOC_TYPES }) {
    return (
        <div className="space-y-2">
            {docs.map((d, i) => (
                <div key={i} className="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-2">
                    <Select className="!w-auto !py-1.5 text-sm" value={d.type} onChange={(e) => onChange(docs.map((x, j) => (j === i ? { ...x, type: e.target.value } : x)))} options={types} />
                    <span className="flex-1 truncate text-sm">{d.file.name}</span>
                    <button type="button" className="text-sm font-semibold text-red-600" onClick={() => onChange(docs.filter((_, j) => j !== i))}>Remove</button>
                </div>
            ))}
            <FileDrop accept="application/pdf,image/*" label="Add verification document" hint="PDF, JPG or PNG up to 5 MB. Stored privately; only our verification team can view it." onChange={(f) => f && onChange([...docs, { type: types[0], file: f }])} />
        </div>
    );
}

/** Opens a private, token-protected file (e.g. a verification document). `path` is relative to /api/v1. */
export async function openPrivate(path) {
    try {
        const blobUrl = URL.createObjectURL(await fetchPrivate(path));
        window.open(blobUrl, '_blank', 'noopener');
    } catch { /* not authorised or missing */ }
}

export default function ShopForm() {
    const state = useApi('/merchant/shop');
    return <Async state={state}>{(d) => <ShopFormInner data={d} reload={state.reload} />}</Async>;
}

function ShopFormInner({ data, reload }) {
    const meta = useMeta();
    const toast = useToast();
    const navigate = useNavigate();
    const { refresh, user } = useAuth();
    const shop = data.shop;
    const { values, setValues, field, set } = useForm({
        name: '', owner_name: user.name, phone: user.phone, whatsapp: user.phone, address: '', area_id: '', maps_url: '', latitude: '', longitude: '',
        opens_at: '09:30', closes_at: '21:00', open_days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'], description: '', pickup_available: true, delivery_available: false,
        delivery_fee: 0, delivery_notes: '', gstin: '', logo: null, photos: [], documents: [], accept_agreement: false,
    });
    useEffect(() => {
        if (shop) setValues((v) => ({ ...v, ...Object.fromEntries(Object.entries(shop).filter(([k]) => k in v && !['logo', 'photos', 'documents'].includes(k)).map(([k, x]) => [k, ['opens_at', 'closes_at'].includes(k) && x ? x.slice(0, 5) : x ?? ''])), gstin: data.profile?.gstin || '' }));
    }, [shop?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    const locate = () => navigator.geolocation?.getCurrentPosition((p) => setValues((v) => ({ ...v, latitude: p.coords.latitude.toFixed(7), longitude: p.coords.longitude.toFixed(7) })), () => toast('Could not get your location', 'error'));
    const save = useAction(() => post(shop ? '/merchant/shop/update' : '/merchant/shop', { ...values, latitude: values.latitude || undefined, longitude: values.longitude || undefined, maps_url: values.maps_url || undefined, gstin: values.gstin || undefined }, { form: true }), {
        onSuccess: (r) => { toast(r.message || 'Shop saved'); refresh(); if (!shop) navigate('/merchant'); else reload(); },
        onError: (e) => toast(e.message, 'error'),
    });
    const removePhoto = useAction((id) => del(`/merchant/shop/photos/${id}`), { onSuccess: reload });
    const e = save.errors;

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title={shop ? 'Shop profile' : 'Register your shop'} subtitle={shop ? null : 'Tell customers about your shop. Our team verifies every shop before it goes live.'} actions={shop && <StatusBadge status={shop.status} />} />
            {shop?.status === 'rejected' && <div className="mb-4"><Alert tone="red" title="Changes required">{shop.rejection_reason}. Saving will resubmit your shop for verification.</Alert></div>}
            <form className="space-y-4" onSubmit={(ev) => { ev.preventDefault(); save.run(); }}>
                <Section title="Shop details">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Shop name" required error={e.name}><Input {...field('name')} /></Field>
                        <Field label="Owner name" required error={e.owner_name}><Input {...field('owner_name')} /></Field>
                        <Field label="Mobile number" required error={e.phone}><Input type="tel" {...field('phone')} /></Field>
                        <Field label="WhatsApp number" error={e.whatsapp} hint="Customers will message you here"><Input type="tel" {...field('whatsapp')} /></Field>
                        <Field label="Description" className="sm:col-span-2"><Textarea {...field('description')} placeholder="What do you specialise in?" /></Field>
                        <Field label="GSTIN (optional)" error={e.gstin}><Input {...field('gstin')} /></Field>
                    </div>
                </Section>
                <Section title="Location">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Address" required error={e.address} className="sm:col-span-2"><Textarea rows={2} {...field('address')} /></Field>
                        <Field label="Area" required error={e.area_id}><Select placeholder="Select area" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} {...field('area_id')} /></Field>
                        <Field label="Google Maps link" error={e.maps_url} hint="Open Google Maps → your shop → Share → copy link"><Input type="url" {...field('maps_url')} placeholder="https://maps.app.goo.gl/…" /></Field>
                        <div className="flex items-end gap-2 sm:col-span-2">
                            <Field label="Latitude" error={e.latitude}><Input {...field('latitude')} /></Field>
                            <Field label="Longitude" error={e.longitude}><Input {...field('longitude')} /></Field>
                            <Button type="button" variant="secondary" onClick={locate}>Use my location</Button>
                        </div>
                    </div>
                </Section>
                <Section title="Hours & fulfilment">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Opens at" required error={e.opens_at}><Input type="time" {...field('opens_at')} /></Field>
                        <Field label="Closes at" required error={e.closes_at}><Input type="time" {...field('closes_at')} /></Field>
                        <Field label="Open days" className="sm:col-span-2"><DaysPicker value={values.open_days} onChange={set('open_days')} /></Field>
                        <Checkbox label="Customers can pick up from the shop" checked={!!values.pickup_available} onChange={set('pickup_available')} />
                        <Checkbox label="We deliver within Bhatkal" checked={!!values.delivery_available} onChange={set('delivery_available')} />
                        {values.delivery_available && <><Field label="Standard delivery fee (₹)" error={e.delivery_fee}><Input type="number" min="0" {...field('delivery_fee')} /></Field><Field label="Delivery notes"><Input {...field('delivery_notes')} placeholder="e.g. Free above ₹999" /></Field></>}
                    </div>
                </Section>
                <Section title="Photos">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Logo" error={e.logo}>{shop?.logo_url && <img src={shop.logo_url} alt="" className="mb-2 h-16 w-16 rounded-xl object-cover" />}<FileDrop files={values.logo} onChange={set('logo')} label="Upload logo" /></Field>
                        <Field label="Shop photos" error={e['photos.0']}>
                            {shop?.photos?.length > 0 && <div className="mb-2 flex flex-wrap gap-2">{shop.photos.map((p) => <div key={p.id} className="relative"><img src={p.url} alt="" className="h-16 w-16 rounded-xl object-cover" /><button type="button" onClick={() => removePhoto.run(p.id)} className="absolute -right-1 -top-1 rounded-full bg-white px-1.5 text-xs shadow" aria-label="Remove photo">✕</button></div>)}</div>}
                            <FileDrop multiple files={values.photos} onChange={set('photos')} />
                        </Field>
                    </div>
                </Section>
                <Section title="Business verification">
                    {shop?.documents?.length > 0 && <ul className="mb-3 flex flex-wrap gap-2">{shop.documents.map((d) => <li key={d.id}><button type="button" className="chip" onClick={() => openPrivate(`/shop-documents/${d.id}`)}>{label(d.type)} · {d.original_name}</button></li>)}</ul>}
                    <DocumentsInput docs={values.documents} onChange={set('documents')} />
                    {e.documents && <p className="mt-2 text-xs font-medium text-red-600">{e.documents[0]}</p>}
                    {!shop && <Checkbox className="mt-4" label="I confirm the details are accurate and agree to the merchant listing agreement (genuine products, honest pricing, timely response to customers)." checked={!!values.accept_agreement} onChange={set('accept_agreement')} />}
                    {e.accept_agreement && <p className="mt-1 text-xs font-medium text-red-600">{e.accept_agreement[0]}</p>}
                </Section>
                <div className="flex items-center gap-3"><Button size="lg" busy={save.busy}>{shop ? 'Save changes' : 'Submit for verification'}</Button>{shop && <Badge>Changes to a live shop go live immediately</Badge>}</div>
            </form>
        </div>
    );
}
