import axios from 'axios';

export const createClientUserAccess = async (payload) => {
    const response = await axios.post(route('api.client-user-access.store'), payload);

    return response.data;
};

export const updateClientUserAccess = async (id, payload) => {
    const response = await axios.put(route('api.client-user-access.update', id), payload);

    return response.data;
};
