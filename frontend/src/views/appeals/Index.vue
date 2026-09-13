<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">申诉复核</h1>
    </div>

    <!-- 状态筛选 -->
    <div class="flex gap-2 flex-wrap">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        @click="changeStatus(tab.value)"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-all"
        :class="statusFilter === tab.value ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'"
      >{{ tab.label }}</button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="appeals.length === 0" class="text-center py-8 text-gray-500 bg-white rounded-lg shadow">
      暂无申诉记录
    </div>
    <div v-else class="bg-white shadow overflow-hidden sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">学生</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">试卷 / 题目</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">类型</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">复核结果</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">提交时间</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="appeal in appeals" :key="appeal.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">{{ appeal.user?.real_name || appeal.user?.username }}</div>
              <div class="text-xs text-gray-400">{{ appeal.user?.email }}</div>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm text-gray-900">{{ appeal.exam_record?.exam_paper?.title }}</div>
              <div class="text-xs text-gray-500 truncate max-w-xs">{{ appeal.question?.title }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ appealTypeLabels[appeal.appeal_type] || appeal.appeal_type }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="appealStatusBadgeClass(appeal.status)">
                {{ appealStatusLabel(appeal.status) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <template v-if="appeal.status === 'completed'">
                <span class="font-medium">{{ appealResultLabel(appeal.result) }}</span>
                <span v-if="Number(appeal.score_delta) !== 0" class="ml-1" :class="Number(appeal.score_delta) > 0 ? 'text-emerald-600' : 'text-red-600'">
                  {{ Number(appeal.score_delta) > 0 ? '+' : '' }}{{ appeal.score_delta }}
                </span>
              </template>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(appeal.created_at).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <button
                v-if="canReview(appeal)"
                @click="openReviewModal(appeal)"
                class="text-indigo-600 hover:text-indigo-900 font-medium mr-3"
              >复核</button>
              <button @click="openTrailModal(appeal)" class="text-emerald-600 hover:text-emerald-900 font-medium">轨迹</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 复核处理模态框 -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showReviewModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
          <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="closeReviewModal"></div>
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all border border-gray-100">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">复核申诉</h3>
                <button @click="closeReviewModal" class="text-gray-400 hover:text-gray-500 bg-white rounded-full p-1 hover:bg-gray-100 transition-colors">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="px-6 py-6 max-h-[calc(100vh-14rem)] overflow-y-auto space-y-5" v-if="reviewAppeal">
                <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
                  <div><span class="text-gray-500">学生：</span><span class="font-medium">{{ reviewAppeal.user?.real_name || reviewAppeal.user?.username }}</span></div>
                  <div><span class="text-gray-500">试卷：</span>{{ reviewAppeal.exam_record?.exam_paper?.title }}</div>
                  <div><span class="text-gray-500">题目：</span>{{ reviewAppeal.question?.title }}</div>
                  <div><span class="text-gray-500">类型：</span>{{ appealTypeLabels[reviewAppeal.appeal_type] || reviewAppeal.appeal_type }}</div>
                  <div><span class="text-gray-500">原因：</span>{{ reviewAppeal.reason }}</div>
                  <div>
                    <button @click="downloadEvidence(reviewAppeal)" class="text-indigo-600 hover:text-indigo-800 font-medium">
                      下载证据{{ reviewAppeal.evidence_name ? `（${reviewAppeal.evidence_name}）` : '' }}
                    </button>
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">处理方式 <span class="text-red-500">*</span></label>
                  <div class="grid grid-cols-2 gap-3">
                    <label
                      v-for="option in availableActions"
                      :key="option.value"
                      class="flex items-center px-3 py-2.5 border rounded-lg cursor-pointer text-sm font-medium transition-all"
                      :class="reviewForm.action === option.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    >
                      <input type="radio" :value="option.value" v-model="reviewForm.action" class="sr-only">
                      {{ option.label }}
                    </label>
                  </div>
                </div>
                <div v-if="reviewForm.action === 'add_score' || reviewForm.action === 'reduce_score'">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    {{ reviewForm.action === 'add_score' ? '加分分值' : '减分分值' }} <span class="text-red-500">*</span>
                  </label>
                  <input v-model.number="reviewForm.score_delta" type="number" min="0.01" max="100" step="0.5" class="input-base" placeholder="请输入分数变动值">
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">处理意见 <span class="text-red-500">*</span></label>
                  <textarea v-model="reviewForm.opinion" rows="4" maxlength="1000" class="input-base resize-y" placeholder="请填写复核处理意见，将保留在处理轨迹中（1000字以内）"></textarea>
                </div>
              </div>
              <div class="bg-gray-50/50 px-6 py-4 flex flex-row-reverse gap-3">
                <button
                  @click="submitReview"
                  :disabled="reviewSubmitting"
                  class="inline-flex justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 min-w-[90px]"
                >{{ reviewSubmitting ? '提交中...' : '提交复核' }}</button>
                <button @click="closeReviewModal" class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">取消</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- 处理轨迹模态框 -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showTrailModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
          <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="showTrailModal = false"></div>
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all border border-gray-100">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">复核处理轨迹</h3>
                <button @click="showTrailModal = false" class="text-gray-400 hover:text-gray-500 bg-white rounded-full p-1 hover:bg-gray-100 transition-colors">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div class="px-6 py-6 max-h-[calc(100vh-14rem)] overflow-y-auto space-y-4" v-if="trailAppeal">
                <div class="bg-gray-50 rounded-xl p-4 text-sm space-y-1">
                  <div><span class="text-gray-500">学生：</span><span class="font-medium">{{ trailAppeal.user?.real_name || trailAppeal.user?.username }}</span></div>
                  <div><span class="text-gray-500">题目：</span>{{ trailAppeal.question?.title }}</div>
                  <div><span class="text-gray-500">申诉原因：</span>{{ trailAppeal.reason }}</div>
                </div>
                <div v-if="!trailAppeal.reviews || trailAppeal.reviews.length === 0" class="text-center py-6 text-gray-400">暂无处理记录</div>
                <ol v-else class="relative border-l border-gray-200 ml-2 space-y-4">
                  <li v-for="review in trailAppeal.reviews" :key="review.id" class="ml-4">
                    <div class="absolute w-3 h-3 bg-indigo-200 rounded-full -left-1.5 border border-white"></div>
                    <div class="text-sm">
                      <span class="font-semibold text-gray-900">{{ review.handler?.real_name || review.handler?.username || '处理人' }}</span>
                      <span class="text-gray-400 text-xs ml-1">({{ roleLabel(review.handler?.role) }})</span>
                      <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full" :class="reviewActionBadgeClass(review.action)">{{ reviewActionLabel(review.action) }}</span>
                      <span v-if="Number(review.score_delta) !== 0" class="ml-1 text-xs" :class="Number(review.score_delta) > 0 ? 'text-emerald-600' : 'text-red-600'">
                        {{ Number(review.score_delta) > 0 ? '+' : '' }}{{ review.score_delta }} 分
                      </span>
                    </div>
                    <div class="mt-1 text-sm text-gray-600 bg-gray-50 rounded-lg p-2.5">{{ review.opinion }}</div>
                    <div class="mt-1 text-xs text-gray-400">{{ new Date(review.created_at).toLocaleString() }}</div>
                  </li>
                </ol>
              </div>
              <div class="bg-gray-50/50 px-6 py-4 flex flex-row-reverse">
                <button @click="showTrailModal = false" class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">关闭</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../api'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../composables/useToast'
import { useModal } from '../../composables/useModal'

const authStore = useAuthStore()
const { success: toastSuccess } = useToast()
const { alert } = useModal()

const appeals = ref([])
const loading = ref(true)
const statusFilter = ref('')

const statusTabs = [
  { value: '', label: '全部' },
  { value: 'pending', label: '待复核' },
  { value: 'forwarded', label: '已转教务' },
  { value: 'completed', label: '复核完成' }
]

const appealTypeLabels = {
  score: '分数异议',
  grading: '判题异议',
  anomaly: '异常标记异议'
}

// 复核处理
const showReviewModal = ref(false)
const reviewAppeal = ref(null)
const reviewSubmitting = ref(false)
const reviewForm = ref({ action: 'maintain', score_delta: null, opinion: '' })

// 轨迹
const showTrailModal = ref(false)
const trailAppeal = ref(null)

const availableActions = computed(() => {
  const actions = [
    { value: 'maintain', label: '维持原判' },
    { value: 'add_score', label: '加分' },
    { value: 'reduce_score', label: '减分' }
  ]
  if (!authStore.isAdmin) {
    actions.push({ value: 'forward', label: '转给教务' })
  }
  return actions
})

onMounted(async () => {
  await loadAppeals()
})

const loadAppeals = async () => {
  loading.value = true
  try {
    const params = { per_page: 50 }
    if (statusFilter.value) params.status = statusFilter.value
    const response = await api.get('/appeals', { params })
    appeals.value = response.data.appeals.data
  } catch (e) {
    console.error('Failed to fetch appeals:', e)
  } finally {
    loading.value = false
  }
}

const changeStatus = (status) => {
  statusFilter.value = status
  loadAppeals()
}

const canReview = (appeal) => {
  if (appeal.status === 'completed') return false
  if (appeal.status === 'forwarded') return authStore.isAdmin
  return true
}

const openReviewModal = (appeal) => {
  reviewAppeal.value = appeal
  reviewForm.value = { action: 'maintain', score_delta: null, opinion: '' }
  showReviewModal.value = true
}

const closeReviewModal = () => {
  if (reviewSubmitting.value) return
  showReviewModal.value = false
}

const submitReview = async () => {
  if ((reviewForm.value.action === 'add_score' || reviewForm.value.action === 'reduce_score')
    && (!reviewForm.value.score_delta || reviewForm.value.score_delta <= 0)) {
    alert('请输入有效的分数变动值', '提示', 'warning')
    return
  }
  if (!reviewForm.value.opinion.trim()) {
    alert('请填写处理意见', '提示', 'warning')
    return
  }
  reviewSubmitting.value = true
  try {
    const payload = {
      action: reviewForm.value.action,
      opinion: reviewForm.value.opinion.trim()
    }
    if (reviewForm.value.action === 'add_score' || reviewForm.value.action === 'reduce_score') {
      payload.score_delta = reviewForm.value.score_delta
    }
    const response = await api.post(`/appeals/${reviewAppeal.value.id}/review`, payload)
    showReviewModal.value = false
    toastSuccess(response.data.message || '复核完成')
    await loadAppeals()
  } catch (e) {
    const errors = e.response?.data?.errors
    const firstError = errors ? Object.values(errors)[0]?.[0] : null
    alert(firstError || e.response?.data?.message || '复核提交失败', '提交失败', 'error')
  } finally {
    reviewSubmitting.value = false
  }
}

const openTrailModal = async (appeal) => {
  trailAppeal.value = appeal
  showTrailModal.value = true
  try {
    const response = await api.get(`/appeals/${appeal.id}`)
    trailAppeal.value = response.data.appeal
  } catch (e) {
    console.error('Failed to fetch appeal detail:', e)
  }
}

const downloadEvidence = async (appeal) => {
  try {
    const response = await api.get(`/appeals/${appeal.id}/evidence`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = appeal.evidence_name || 'evidence'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (e) {
    alert('证据下载失败', '下载失败', 'error')
  }
}

const appealStatusLabel = (status) => ({
  pending: '待复核',
  forwarded: '已转教务',
  completed: '复核完成'
}[status] || status)

const appealStatusBadgeClass = (status) => ({
  pending: 'bg-yellow-100 text-yellow-800',
  forwarded: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800'
}[status] || 'bg-gray-100 text-gray-800')

const appealResultLabel = (result) => ({
  maintain: '维持原判',
  add_score: '加分',
  reduce_score: '减分'
}[result] || result || '')

const reviewActionLabel = (action) => ({
  maintain: '维持原判',
  add_score: '加分',
  reduce_score: '减分',
  forward: '转给教务'
}[action] || action)

const reviewActionBadgeClass = (action) => ({
  maintain: 'bg-gray-100 text-gray-700',
  add_score: 'bg-emerald-100 text-emerald-700',
  reduce_score: 'bg-red-100 text-red-700',
  forward: 'bg-blue-100 text-blue-700'
}[action] || 'bg-gray-100 text-gray-700')

const roleLabel = (role) => ({
  admin: '教务',
  teacher: '教师',
  student: '学生'
}[role] || role || '')
</script>
