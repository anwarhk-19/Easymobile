import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import { TechnicianCard } from '../../components/cards';
import { Async, Badge, Button, CardGridSkeleton, Checkbox, DL, Empty, PageHeader, Pagination, Section, Select, Stars, WhatsAppButton } from '../../components/ui';
import { ReviewList } from './ProductDetail';
import { useApi, useMeta } from '../../lib/hooks';
import { useAuth } from '../../lib/auth';
import { time12 } from '../../lib/format';

export function RepairsHome() {
    const meta = useMeta();
    const [params, setParams] = useSearchParams();
    const set = (k, v) => { const n = new URLSearchParams(params); v ? n.set(k, v) : n.delete(k); n.delete('page'); setParams(n); };
    const q = { repair_category: params.get('category'), brand_id: params.get('brand_id'), area_id: params.get('area_id'), pickup: params.get('pickup'), page: params.get('page') };
    const state = useApi('/technicians', q);

    return (
        <div>
            <section className="mb-6 rounded-3xl bg-gradient-to-br from-accent-50 to-white p-5 ring-1 ring-accent-100 sm:p-7">
                <h1 className="text-2xl font-extrabold text-slate-900 sm:text-3xl">Mobile repairs you can trust</h1>
                <ol className="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-4">
                    {[['message', 'Describe the problem'], ['list', 'Get a written quotation'], ['check', 'Approve before work starts'], ['shield', 'Warranty on every repair']].map(([i, t], n) => (
                        <li key={t} className="flex items-center gap-2"><span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-accent-600 shadow-sm"><Icon name={i} className="h-4 w-4" /></span><span><b>{n + 1}.</b> {t}</span></li>
                    ))}
                </ol>
            </section>
            <div className="no-scrollbar -mx-4 mb-4 flex gap-2 overflow-x-auto px-4">
                <button className={`chip ${!params.get('category') ? 'chip-active' : ''}`} onClick={() => set('category', '')}>All repairs</button>
                {meta?.repair_categories?.map((c) => <button key={c.id} className={`chip ${params.get('category') === c.slug ? 'chip-active' : ''}`} onClick={() => set('category', c.slug)}><Icon name={c.icon} className="h-4 w-4" />{c.name}</button>)}
            </div>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Select aria-label="Phone brand" className="!w-auto" value={params.get('brand_id') || ''} onChange={(e) => set('brand_id', e.target.value)} placeholder="Any brand" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} />
                <Select aria-label="Area" className="!w-auto" value={params.get('area_id') || ''} onChange={(e) => set('area_id', e.target.value)} placeholder="All areas" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} />
                <Checkbox label="Pickup & drop" checked={params.get('pickup') === '1'} onChange={(e) => set('pickup', e.target.checked ? '1' : '')} />
            </div>
            <Async state={state} skeleton={<CardGridSkeleton count={6} />}>
                {(d) => d.data.length === 0 ? <Empty icon="wrench" title="No technicians match">Try a different repair type or area.</Empty> : (
                    <><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{d.data.map((t) => <TechnicianCard key={t.id} tech={t} />)}</div><Pagination meta={d} onPage={(p) => set('page', String(p))} /></>
                )}
            </Async>
        </div>
    );
}

export function TechnicianDetail() {
    const { slug } = useParams();
    const state = useApi(`/technicians/${slug}`);
    const { user } = useAuth();
    const navigate = useNavigate();
    return (
        <Async state={state}>
            {({ technician: t, whatsapp_link, reviews }) => (
                <div>
                    <PageHeader back="/repairs" title={t.name} subtitle={`${t.shop_name ? t.shop_name + ' · ' : ''}${t.area?.name} · ${t.experience_years}+ years experience`}
                        actions={<><WhatsAppButton href={whatsapp_link} /><Button onClick={() => navigate(user ? `/repairs/new?technician=${t.id}` : `/login?next=/repairs/new?technician=${t.id}`)}><Icon name="wrench" />Request repair</Button></>} />
                    <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
                        <div className="space-y-4">
                            <Section title="About">
                                {t.bio && <p className="mb-4 text-sm text-slate-700">{t.bio}</p>}
                                <DL items={[['Rating', <Stars key="s" value={t.rating_avg} count={t.rating_count} />], ['Completed repairs', t.completed_repairs], ['Working hours', `${time12(t.opens_at)} – ${time12(t.closes_at)}`],
                                    ['Service modes', ['Walk-in', t.pickup_available && 'Pickup & drop', t.onsite_available && 'On-site'].filter(Boolean).join(', ')], ['Certifications', t.certifications || '—'], ['Service areas', t.service_areas.map((a) => a.name).join(', ')]]} />
                            </Section>
                            <Section title="Repairs offered"><div className="flex flex-wrap gap-2">{t.repair_categories.map((c) => <Badge key={c.id} tone="amber">{c.name}</Badge>)}</div></Section>
                            <Section title="Brands supported"><div className="flex flex-wrap gap-2">{t.brands.map((b) => <Badge key={b.id}>{b.name}</Badge>)}</div>{t.supported_models_note && <p className="mt-3 text-sm text-slate-600">{t.supported_models_note}</p>}</Section>
                        </div>
                        <div className="space-y-4">
                            <Section title="Warranty"><p className="text-sm text-slate-700"><b>{t.default_warranty_days} days</b> standard. {t.warranty_terms}</p><p className="mt-2 text-xs text-slate-500">Exact warranty and parts type are stated on each quotation.</p></Section>
                            <Section title="Reviews"><ReviewList reviews={reviews} /></Section>
                            <Link to="/support/new" className="block text-center text-xs text-slate-500 hover:text-brand-700">Report a problem with this technician</Link>
                        </div>
                    </div>
                </div>
            )}
        </Async>
    );
}
