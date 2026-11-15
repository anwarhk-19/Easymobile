import { Route, Routes } from 'react-router-dom';
import NotFound from '../public/NotFound';
import Dashboard from './Dashboard';
import ShopForm from './ShopForm';
import { ProductForm, Products } from './Products';
import { MerchantOrderDetail, MerchantOrders } from './Orders';
import { MerchantEnquiries, MerchantReports, MerchantSettlements } from './Other';

export default function MerchantRoutes() {
    return (
        <Routes>
            <Route index element={<Dashboard />} />
            <Route path="shop" element={<ShopForm />} />
            <Route path="products" element={<Products />} />
            <Route path="products/new" element={<ProductForm />} />
            <Route path="products/:id" element={<ProductForm />} />
            <Route path="orders" element={<MerchantOrders />} />
            <Route path="orders/:id" element={<MerchantOrderDetail />} />
            <Route path="enquiries" element={<MerchantEnquiries />} />
            <Route path="reports" element={<MerchantReports />} />
            <Route path="settlements" element={<MerchantSettlements />} />
            <Route path="*" element={<NotFound />} />
        </Routes>
    );
}
