import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import SearchBar from '../../components/SearchBar';
import { ProductCard, ShopCard, TechnicianCard } from '../../components/cards';
import { Async, Button, CardGridSkeleton, Checkbox, Empty, Field, Input, Pagination, Select, Tabs } from '../../components/ui';
import { useApi, useMeta } from '../../lib/hooks';
import { get } from '../../lib/api';

function NoResults({ q, data }) {
    return (
        <Empty icon="search" title={q ? `No results for “${q}”` : 'No matching products'}
            action={<div className="flex flex-wrap justify-center gap-2"><Button as={Link} to="/shops" variant="secondary">Ask a shop directly</Button><Button as={Link} to="/repairs">Looking for a repair?</Button></div>}>
            <ul className="mt-1 space-y-1 text-left">{(data?.suggestions?.tips || ['Try fewer words or a different spelling.', 'Clear some filters.']).map((t) => <li key={t}>• {t}</li>)}</ul>
            {data?.suggestions?.popular_categories?.length > 0 && (
                <div className="mt-4 flex flex-wrap justify-center gap-2">{data.suggestions.popular_categories.map((c) => <Link key={c.id} to={`/search?category=${c.slug}`} className="chip">{c.name}</Link>)}</div>
            )}
            <p className="mt-3 text-xs text-slate-400">We record searches with no results to help shops add what Bhatkal is looking for.</p>
        </Empty>
    );
}

function ProductFilters({ params, set, meta }) {
    const [models, setModels] = useState([]);
    const brand = params.get('brand_id');
    useEffect(() => {
        if (!brand) { setModels([]); return; }
        get('/phone-models', { brand_id: brand }).then(setModels).catch(() => setModels([]));
    }, [brand]);
    return (
        <div className="space-y-4">
            <Field label="Category"><Select value={params.get('category') || ''} onChange={(e) => set({ category: e.target.value })} placeholder="All categories" options={(meta?.categories || []).map((c) => ({ value: c.slug, label: c.name }))} /></Field>
            <Field label="Mobile brand"><Select value={brand || ''} onChange={(e) => set({ brand_id: e.target.value, phone_model_id: '' })} placeholder="Any brand" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} /></Field>
            {models.length > 0 && <Field label="Compatible model"><Select value={params.get('phone_model_id') || ''} onChange={(e) => set({ phone_model_id: e.target.value })} placeholder="Any model" options={models.map((m) => ({ value: m.id, label: m.name }))} /></Field>}
            <Field label="Area"><Select value={params.get('area_id') || ''} onChange={(e) => set({ area_id: e.target.value })} placeholder="All of Bhatkal" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} /></Field>
            <div className="grid grid-cols-2 gap-2">
                <Field label="Min ₹"><Input type="number" min="0" inputMode="numeric" defaultValue={params.get('min_price') || ''} onBlur={(e) => set({ min_price: e.target.value })} /></Field>
                <Field label="Max ₹"><Input type="number" min="0" inputMode="numeric" defaultValue={params.get('max_price') || ''} onBlur={(e) => set({ max_price: e.target.value })} /></Field>
            </div>
            <Field label="Availability"><Select value={params.get('availability') || ''} onChange={(e) => set({ availability: e.target.value })} placeholder="Any" options={[{ value: 'available', label: 'Available now' }, { value: 'on_order', label: 'On order' }, { value: 'out_of_stock', label: 'Out of stock' }]} /></Field>
            <Checkbox label="Shops that deliver" checked={params.get('delivery') === '1'} onChange={(e) => set({ delivery: e.target.checked ? '1' : '' })} />
        </div>
    );
}

export default function Search() {
    const [params, setParams] = useSearchParams();
    const meta = useMeta();
    const [showFilters, setShowFilters] = useState(false);
    const tab = params.get('tab') || 'products';
    const q = params.get('q') || '';
    const set = (patch) => {
        const next = new URLSearchParams(params);
        Object.entries(patch).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
        if (!('page' in patch)) next.delete('page');
        setParams(next);
    };
    const query = Object.fromEntries([...params.entries()].filter(([k]) => k !== 'tab'));
    const endpoint = { products: '/products', shops: '/shops', technicians: '/technicians' }[tab];
    const res = useApi(endpoint, query);
    const activeFilters = ['category', 'brand_id', 'phone_model_id', 'area_id', 'min_price', 'max_price', 'availability', 'delivery'].filter((k) => params.get(k)).length;

    return (
        <div>
            <div className="mb-4"><SearchBar key={q} initial={q} /></div>
            <Tabs value={tab} onChange={(t) => set({ tab: t === 'products' ? '' : t })} tabs={[['products', 'Products'], ['shops', 'Shops'], ['technicians', 'Repair technicians']]} />

            <div className="mt-5 grid gap-6 lg:grid-cols-[260px_1fr]">
                {tab === 'products' && (
                    <aside className={`${showFilters ? 'block' : 'hidden'} card h-fit p-4 lg:block`}>
                        <div className="mb-3 flex items-center justify-between"><p className="font-bold text-slate-900">Filters</p>{activeFilters > 0 && <button className="text-sm font-semibold text-brand-700" onClick={() => setParams(q ? { q } : {})}>Clear all</button>}</div>
                        <ProductFilters params={params} set={set} meta={meta} />
                    </aside>
                )}
                <div className={tab === 'products' ? '' : 'lg:col-span-2'}>
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-slate-600">{res.data ? <><b className="text-slate-900">{res.data.total}</b> {tab} {q && <>for “{q}”</>}</> : ' '}</p>
                        {tab === 'products' && (
                            <div className="flex gap-2">
                                <Button variant="secondary" size="sm" className="lg:hidden" onClick={() => setShowFilters((s) => !s)}><Icon name="list" className="h-4 w-4" />Filters{activeFilters ? ` (${activeFilters})` : ''}</Button>
                                <Select aria-label="Sort by" className="!w-auto !py-1.5 text-sm" value={params.get('sort') || 'relevance'} onChange={(e) => set({ sort: e.target.value === 'relevance' ? '' : e.target.value })}
                                    options={[{ value: 'relevance', label: 'Best match' }, { value: 'price_asc', label: 'Price: low to high' }, { value: 'price_desc', label: 'Price: high to low' }, { value: 'newest', label: 'Newest' }, { value: 'rating', label: 'Top rated' }]} />
                            </div>
                        )}
                    </div>
                    <Async state={res} skeleton={<CardGridSkeleton />}>
                        {(d) => d.data.length === 0 ? <NoResults q={q} data={d} /> : (
                            <>
                                {tab === 'products' && <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">{d.data.map((p) => <ProductCard key={p.id} product={p} />)}</div>}
                                {tab === 'shops' && <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{d.data.map((s) => <ShopCard key={s.id} shop={s} />)}</div>}
                                {tab === 'technicians' && <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{d.data.map((t) => <TechnicianCard key={t.id} tech={t} />)}</div>}
                                <Pagination meta={d} onPage={(p) => set({ page: String(p) })} />
                            </>
                        )}
                    </Async>
                </div>
            </div>
        </div>
    );
}
