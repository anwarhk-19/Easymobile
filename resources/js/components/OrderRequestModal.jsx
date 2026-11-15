import { useNavigate } from 'react-router-dom';
import { Alert, Button, Field, Input, Modal, Select, Textarea } from './ui';
import { post } from '../lib/api';
import { useAction, useForm, useMeta } from '../lib/hooks';
import { useToast } from '../lib/toast';
import { money } from '../lib/format';

export default function OrderRequestModal({ open, onClose, product, shop, enquiryId }) {
    const meta = useMeta();
    const toast = useToast();
    const navigate = useNavigate();
    const { values, field, set } = useForm({ quantity: 1, colour: '', variant: '', model_note: '', fulfilment: shop.pickup_available ? 'pickup' : 'delivery', delivery_address: '', delivery_area_id: '', payment_method: '', customer_note: '' });
    const submit = useAction(() => post('/orders', {
        shop_id: shop.id, enquiry_id: enquiryId,
        items: [{ product_id: product.id, quantity: Number(values.quantity), colour: values.colour, variant: values.variant, model_note: values.model_note }],
        fulfilment: values.fulfilment, delivery_address: values.delivery_address, delivery_area_id: values.delivery_area_id || undefined,
        payment_method: values.payment_method || undefined, customer_note: values.customer_note,
    }), { onSuccess: (r) => { toast(r.message); navigate(`/orders/${r.order.id}`); }, onError: (e) => toast(e.message, 'error') });
    const e = submit.errors;

    return (
        <Modal open={open} onClose={onClose} title="Request this product"
            footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button busy={submit.busy} onClick={() => submit.run()}>Send order request</Button></>}>
            <div className="space-y-4">
                <Alert tone="blue" icon="message" title="This is a request, not a confirmed order">{shop.name} will confirm exact model, colour, final price and delivery fee. You accept the quotation before anything is confirmed.</Alert>
                <div className="rounded-xl bg-slate-50 p-3 text-sm"><p className="font-semibold text-slate-900">{product.name}</p><p className="text-slate-600">Listed at {money(product.effective_price)} · {shop.name}</p></div>
                <div className="grid grid-cols-3 gap-3">
                    <Field label="Qty" error={e['items.0.quantity']}><Input type="number" min="1" max="20" {...field('quantity')} /></Field>
                    <Field label="Colour" className="col-span-2"><Input placeholder="e.g. Black" {...field('colour')} /></Field>
                </div>
                <Field label="Variant / model notes" hint="e.g. 8GB/128GB, or the phone model you need it for"><Input {...field('variant')} /></Field>
                <Field label="How would you like to get it?">
                    <div className="grid grid-cols-2 gap-2">
                        {[['pickup', 'Pickup from shop', shop.pickup_available], ['delivery', `Delivery${Number(shop.delivery_fee) ? ` (≈ ${money(shop.delivery_fee)})` : ''}`, shop.delivery_available]].map(([v, t, ok]) => (
                            <button key={v} type="button" disabled={!ok} onClick={() => set('fulfilment')(v)} className={`rounded-xl border px-3 py-2.5 text-sm font-semibold transition disabled:opacity-40 ${values.fulfilment === v ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-300 text-slate-700'}`}>{t}{!ok && <span className="block text-xs font-normal">Not offered</span>}</button>
                        ))}
                    </div>
                </Field>
                {values.fulfilment === 'delivery' && (
                    <>
                        <Field label="Delivery area" required error={e.delivery_area_id}><Select placeholder="Select area" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} {...field('delivery_area_id')} /></Field>
                        <Field label="Delivery address" required error={e.delivery_address}><Textarea rows={2} {...field('delivery_address')} /></Field>
                    </>
                )}
                <Field label="Preferred payment" hint="Pay at the shop or on delivery. UPI references are recorded by the shop."><Select placeholder="Decide later" {...field('payment_method')} options={[{ value: 'cod', label: 'Cash on delivery' }, { value: 'pickup_payment', label: 'Pay at shop pickup' }, { value: 'upi', label: 'UPI' }]} /></Field>
                <Field label="Note to shop"><Textarea rows={2} {...field('customer_note')} /></Field>
            </div>
        </Modal>
    );
}
