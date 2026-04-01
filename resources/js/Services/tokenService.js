import axios from 'axios';

export const revokeToken = async (tokenId, payload) => {
    return axios.post(route('admin.tokens.revoke', tokenId), payload);
};

export const revokeTokenFamily = async (familyId, payload) => {
    return axios.post(route('admin.tokens.revoke-family', familyId), payload);
};
