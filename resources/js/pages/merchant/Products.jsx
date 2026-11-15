import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import DataTable, { Toolbar } from '../../components/DataTable';
import { Freshness, ProductImage } from '../../components/cards';
import { Alert, Async, Badge, Button, ConfirmDialog, Field, FileDrop, Input, Modal, PageHeader, Section, Select, StatusBadge, Tabs, Textarea } from '../../components/ui';
import { del, get, post } from '../../lib/api';
import { useAction, useApi, useDebounced, useForm, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { dateTime, label, money } from '../../lib/format';

function StockModal({ product, onClose, onDone }) {
    const toast = useToast();
    const [quantity, setQuantity] = useState(product.stock_quantity);
    const [reason, setReason] = useState('restock');
    const [note, setNote] = useState('');
    const logs = useApi(`/merchant/products/${product.id}/stock-logs`);
    const save = useAction(() => post(`/merchant/products/${product.id}/stock`, { quantity: Number(quantity), reason, note }), { onSuccess: () => { toast('Stock updated'); onDone(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Modal open onClose={onClose} title={`Stock · ${product.name}`} footer={<><Button variant="secondary" onClick={onClose}>Close</Button><Button busy={save.busy} onClick={() => save.run()}>Save stock</Button></>}>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Quantity in shop"><Input type="number" min="0" value={quantity} onChange={(e) => setQuantity(e.target.value)} /></Field>
                <Field label="Reason"><Select value={reason} onChange={(e) => setReason(e.target.value)} options={['restock', 'sale_offline', 'damaged', 'returned', 'correction', 'confirmed']} /></Field>
                <Field label="Note" className="col-span-2"><Input value={note} onChange={(e) => setNote(e.target.value)} /></Field>
            </div>
            <p className="mb-2 mt-5 text-sm font-bold text-slate-900">Stock history</p>
            <Async state={logs}>{(d) => (
                <ul className="max-h-56 divide-y divide-slate-100 overflow-y-auto text-sm">{d.data.map((l) => (
                    <li key={l.id} className="flex justify-between gap-2 py-2"><span>{label(l.reason)}{l.note && <span className="text-slate-500"> · {l.note}</span>}<span className="block text-xs text-slate-400">{dateTime(l.created_at)}{l.user && ` · ${l.user.name}`}</span></span><span className={`font-semibold ${l.change < 0 ? 'text-red-600' : 'text-emerald-700'}`}>{l.change > 0 ? '+' : ''}{l.change} → {l.new_quantity}</span></li>
                ))}</ul>
            )}</Async>
        </Modal>
    );
}

export function Products() {
    const [params, setParams] = useSearchParams();
    const toast = useToast();
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const [stockFor, setStockFor] = useState(null);
    const status = params.get('status') || '';
    const state = useApi('/merchant/products', { q: dq, status, page });
    const confirm = useAction((id) => post(`/merchant/products/${id}/confirm-stock`), { onSuccess: () => { toast('Stock confirmed'); state.reload(); } });
    const confirmAll = useAction(() => post('/merchant/products/confirm-stock'), { onSuccess: (r) => { toast(r.message); state.reload(); } });
    const toggle = useAction((id) => post(`/merchant/products/${id}/toggle`), { onSuccess: () => state.reload() });

    return (
        <div>
            <PageHeader title="Products" actions={<><Button variant="secondary" busy={confirmAll.busy} onClick={() => confirmAll.run()}><Icon name="check" className="h-4 w-4" />Confirm all stock</Button><Button as={Link} to="/merchant/products/new"><Icon name="plus" className="h-4 w-4" />Add product</Button></>} />
            <Tabs className="mb-3" value={status} onChange={(s) => { setParams(s ? { status: s } : {}); setPage(1); }} tabs={[['', 'All'], ['active', 'Active'], ['inactive', 'Inactive'], ['out_of_stock', 'Out of stock'], ['stale', 'Stock needs check'], ['suspended', 'Suspended']]} />
            <Toolbar><Input type="search" placeholder="Search name or SKU" value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No products found" emptyIcon="box" columns={[
                { key: 'name', label: 'Product', render: (p) => (
                    <div className="flex min-w-56 items-center gap-3"><div className="h-12 w-12 shrink-0 overflow-hidden rounded-lg"><ProductImage product={p} className="h-12" /></div>
                        <div><Link to={`/merchant/products/${p.id}`} className="font-semibold text-slate-900 hover:text-brand-700">{p.name}</Link><p className="text-xs text-slate-500">{p.sku} · {p.category?.name}</p>{p.is_suspended && <Badge tone="red">Suspended: {p.suspension_reason}</Badge>}</div></div>) },
                { key: 'price', label: 'Price', render: (p) => <><b>{money(p.effective_price)}</b>{p.discount_price && <span className="block text-xs text-slate-400 line-through">{money(p.price)}</span>}</> },
                { key: 'stock', label: 'Stock', render: (p) => <div className="min-w-36"><div className="flex items-center gap-2"><b>{p.stock_quantity}</b><StatusBadge status={p.availability} /></div><Freshness freshness={p.stock_freshness} /></div> },
                { key: 'status', label: 'Status', render: (p) => <button onClick={() => toggle.run(p.id)} title="Toggle active"><StatusBadge status={p.status} /></button> },
                { key: 'actions', label: '', render: (p) => <div className="flex gap-1.5"><Button size="sm" variant="secondary" onClick={() => setStockFor(p)}>Update stock</Button><Button size="sm" variant="ghost" onClick={() => confirm.run(p.id)} title="Stock is still accurate">Confirm</Button></div> },
            ]} />
            {stockFor && <StockModal product={stockFor} onClose={() => setStockFor(null)} onDone={() => { setStockFor(null); state.reload(); }} />}
        </div>
    );
}

export function ProductForm() {
    const { id } = useParams();
    const state = useApi(id ? `/merchant/products/${id}` : null);
    if (id) return <Async state={state}>{(d) => <ProductFormInner product={d.product} reload={state.reload} />}</Async>;
    return <ProductFormInner />;
}

function ModelPicker({ value, onChange, initial = [] }) {
    const meta = useMeta();
    const [brand, setBrand] = useState('');
    const [models, setModels] = useState([]);
    const [known, setKnown] = useState(() => Object.fromEntries(initial.map((m) => [m.id, m])));
    useEffect(() => { if (brand) get('/phone-models', { brand_id: brand }).then(setModels); }, [brand]);
    const toggle = (m) => { setKnown((k) => ({ ...k, [m.id]: m })); onChange(value.includes(m.id) ? value.filter((x) => x !== m.id) : [...value, m.id]); };
    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-1.5">{value.map((mid) => <button type="button" key={mid} onClick={() => onChange(value.filter((x) => x !== mid))} className="chip chip-active !py-1 text-xs">{known[mid]?.brand?.name} {known[mid]?.name || `#${mid}`} ✕</button>)}{!value.length && <span className="text-sm text-slate-500">No models selected (universal accessory).</span>}</div>
            <Select value={brand} onChange={(e) => setBrand(e.target.value)} placeholder="Pick a brand to add models…" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} />
            {models.length > 0 && <div className="flex flex-wrap gap-1.5">{models.map((m) => <button type="button" key={m.id} onClick={() => toggle({ ...m, brand: meta.brands.find((b) => b.id === m.brand_id) })} className={`chip !py-1 text-xs ${value.includes(m.id) ? 'chip-active' : ''}`}>{m.name}</button>)}</div>}
        </div>
    );
}

function ProductFormInner({ product, reload }) {
    const meta = useMeta();
    const navigate = useNavigate();
    const toast = useToast();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { values, field, set } = useForm({
        name: product?.name || '', category_id: product?.category_id || '', brand_id: product?.brand_id || '', description: product?.description || '', sku: product?.sku || '',
        price: product?.price || '', discount_price: product?.discount_price || '', stock_quantity: product?.stock_quantity ?? 1, availability: product?.availability || 'in_stock',
        status: product?.status || 'active', compatible_model_ids: product?.compatible_models?.map((m) => m.id) || [], images: [],
    });
    const save = useAction(() => post(product ? `/merchant/products/${product.id}` : '/merchant/products', { ...values, brand_id: values.brand_id || undefined, discount_price: values.discount_price || undefined }, { form: true }),
        { onSuccess: (r) => { toast('Product saved'); product ? reload() : navigate(`/merchant/products/${r.product.id}`); }, onError: (e) => toast(e.message, 'error') });
    const removeImage = useAction((img) => del(`/merchant/products/${product.id}/images/${img}`), { onSuccess: reload });
    const destroy = useAction(() => del(`/merchant/products/${product.id}`), { onSuccess: () => { toast('Product deleted'); navigate('/merchant/products'); } });
    const e = save.errors;

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader back="/merchant/products" title={product ? 'Edit product' : 'Add product'} actions={product && <Button variant="ghost" onClick={() => setConfirmDelete(true)}>Delete</Button>} />
            {product?.is_suspended && <div className="mb-4"><Alert tone="red" title="This listing is suspended by the admin">{product.suspension_reason}</Alert></div>}
            <form className="space-y-4" onSubmit={(ev) => { ev.preventDefault(); save.run(); }}>
                <Section title="Basics">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Product name" required error={e.name} className="sm:col-span-2"><Input {...field('name')} placeholder="e.g. Samsung 25W USB-C Fast Charger" /></Field>
                        <Field label="Category" required error={e.category_id}><Select placeholder="Select" options={(meta?.categories || []).map((c) => ({ value: c.id, label: c.name }))} {...field('category_id')} /></Field>
                        <Field label="Product brand" error={e.brand_id}><Select placeholder="Unbranded / other" options={(meta?.brands || []).map((b) => ({ value: b.id, label: b.name }))} {...field('brand_id')} /></Field>
                        <Field label="SKU" required error={e.sku} hint="Your own code — unique within your shop"><Input {...field('sku')} /></Field>
                        <Field label="Status"><Select options={['active', 'inactive']} {...field('status')} /></Field>
                        <Field label="Description" className="sm:col-span-2"><Textarea rows={4} {...field('description')} /></Field>
                    </div>
                </Section>
                <Section title="Price & stock">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Selling price (MRP) ₹" required error={e.price}><Input type="number" min="1" step="0.01" {...field('price')} /></Field>
                        <Field label="Discount price ₹" error={e.discount_price} hint="Leave blank if no discount"><Input type="number" min="1" step="0.01" {...field('discount_price')} /></Field>
                        <Field label="Stock quantity" required error={e.stock_quantity} hint={product && 'Changes are recorded in stock history'}><Input type="number" min="0" {...field('stock_quantity')} /></Field>
                        <Field label="Availability" error={e.availability} hint="Updates automatically from stock; ‘On order’ is kept when stock is 0"><Select options={meta?.enums?.availability || []} {...field('availability')} /></Field>
                    </div>
                </Section>
                <Section title="Compatible phone models"><ModelPicker value={values.compatible_model_ids} onChange={set('compatible_model_ids')} initial={product?.compatible_models} /></Section>
                <Section title="Images">
                    {product?.images?.length > 0 && <div className="mb-3 flex flex-wrap gap-2">{product.images.map((im) => <div key={im.id} className="relative"><img src={im.url} alt="" className="h-20 w-20 rounded-xl object-cover" /><button type="button" className="absolute -right-1 -top-1 rounded-full bg-white px-1.5 text-xs shadow" onClick={() => removeImage.run(im.id)} aria-label="Remove image">✕</button></div>)}</div>}
                    <FileDrop multiple files={values.images} onChange={set('images')} hint="JPG, PNG or WEBP up to 5 MB, max 8" />
                    {e['images.0'] && <p className="mt-1 text-xs text-red-600">{e['images.0'][0]}</p>}
                </Section>
                <Button size="lg" busy={save.busy}>{product ? 'Save changes' : 'Add product'}</Button>
            </form>
            <ConfirmDialog open={confirmDelete} onClose={() => setConfirmDelete(false)} tone="danger" title="Delete product?" message="It will be removed from the marketplace. Past orders keep their records." confirmText="Delete" busy={destroy.busy} onConfirm={() => destroy.run()} />
        </div>
    );
}
