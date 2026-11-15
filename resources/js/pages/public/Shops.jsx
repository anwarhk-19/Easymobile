import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { ProductCard, ShopCard } from '../../components/cards';
import EnquiryModal from '../../components/EnquiryModal';
import { Async, Button, CardGridSkeleton, Checkbox, Empty, Pagination, PageHeader, Section, Select, Stars, Tabs, WhatsAppButton } from '../../components/ui';
import { ReviewList, ShopInfo } from './ProductDetail';
import { useApi, useMeta } from '../../lib/hooks';
import { useAuth } from '../../lib/auth';

export function Shops() {
    const meta = useMeta();
    const [params, setParams] = useSearchParams();
    const set = (k, v) => { const n = new URLSearchParams(params); v ? n.set(k, v) : n.delete(k); n.delete('page'); setParams(n); };
    const state = useApi('/shops', Object.fromEntries(params));
    return (
        <div>
            <PageHeader title="Mobile shops in Bhatkal" subtitle="Verified local shops. Check hours, delivery and ratings before you visit." />
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Select aria-label="Area" className="!w-auto" value={params.get('area_id') || ''} onChange={(e) => set('area_id', e.target.value)} placeholder="All areas" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} />
                <Checkbox label="Offers delivery" checked={params.get('delivery') === '1'} onChange={(e) => set('delivery', e.target.checked ? '1' : '')} />
            </div>
            <Async state={state} skeleton={<CardGridSkeleton count={6} />}>
                {(d) => d.data.length === 0 ? <Empty icon="store" title="No shops match these filters">Try another area or remove the delivery filter.</Empty> : (
                    <><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{d.data.map((s) => <ShopCard key={s.id} shop={s} />)}</div><Pagination meta={d} onPage={(p) => set('page', String(p))} /></>
                )}
            </Async>
        </div>
    );
}

export function ShopDetail() {
    const { slug } = useParams();
    const state = useApi(`/shops/${slug}`);
    const { user } = useAuth();
    const navigate = useNavigate();
    const [cat, setCat] = useState('');
    const [page, setPage] = useState(1);
    const [enquire, setEnquire] = useState(false);
    const shopId = state.data?.shop?.id;
    const products = useApi(shopId ? '/products' : null, { shop_id: shopId, category: cat, page });

    return (
        <Async state={state}>
            {({ shop, whatsapp_link, categories, reviews }) => (
                <div>
                    <div className="card overflow-hidden">
                        {shop.photos?.[0] && <img src={shop.photos[0].url} alt="" className="h-40 w-full object-cover sm:h-56" />}
                        <div className="flex flex-wrap items-start justify-between gap-4 p-5">
                            <div>
                                <h1 className="text-2xl font-extrabold text-slate-900">{shop.name}</h1>
                                <div className="mt-1"><Stars value={shop.rating_avg} count={shop.rating_count} /></div>
                                {shop.description && <p className="mt-2 max-w-2xl text-sm text-slate-600">{shop.description}</p>}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <WhatsAppButton href={whatsapp_link} />
                                <Button variant="secondary" onClick={() => (user?.role === 'customer' ? setEnquire(true) : navigate(`/login?next=/shops/${slug}`))}>Send enquiry</Button>
                            </div>
                        </div>
                    </div>
                    <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_340px]">
                        <div>
                            <Tabs value={cat} onChange={(c) => { setCat(c); setPage(1); }} tabs={[['', 'All'], ...categories.map((c) => [c.slug, c.name])]} className="mb-4" />
                            <Async state={products} skeleton={<CardGridSkeleton count={6} />}>
                                {(d) => d.data.length === 0 ? <Empty title="No products listed yet">Contact the shop directly — they may have what you need.</Empty> : (
                                    <><div className="grid grid-cols-2 gap-3 sm:grid-cols-3">{d.data.map((p) => <ProductCard key={p.id} product={p} />)}</div><Pagination meta={d} onPage={setPage} /></>
                                )}
                            </Async>
                        </div>
                        <div className="space-y-4">
                            <Section title="Shop details"><ShopInfo shop={shop} /></Section>
                            <Section title="Customer reviews"><ReviewList reviews={reviews} /></Section>
                            <Link to="/support/new" className="block text-center text-xs text-slate-500 hover:text-brand-700">Report a problem with this shop</Link>
                        </div>
                    </div>
                    <EnquiryModal open={enquire} onClose={() => setEnquire(false)} shop={shop} />
                </div>
            )}
        </Async>
    );
}
