
#include <pthread.h>
#include <semaphore.h>

#define MAX_ACTIVITY_SIZE 1024

struct activity_queue{
	sem_t* activities[MAX_ACTIVITY_SIZE];
	int n; //Number of activities in queue
	int head;
	int tail;

};

struct threadarg{
	struct activity_queue* queue;
	sem_t* sem;
	sem_t* master_sem;
	void* args;
};

void enqueue(struct activity_queue* q, sem_t* new_sem){
	q->activities[q->tail] = new_sem;
	q->tail = (q->tail + 1) % MAX_ACTIVITY_SIZE;
	q->n++;
}

void yield(sem_t* my_sem, sem_t* master_sem, struct activity_queue* q){
	//enqueue self??
	enqueue(q, my_sem);
	sem_post(master_sem);
	sem_wait(my_sem);
}

void add_activity(struct activity_queue* q, sem_t* master_sem, sem_t* my_sem, void* func_pointer, struct threadarg* arg){
	//make a new thread
	//immediately wait in new thread
	pthread_t* new_activity;
	sem_t* new_sem;
	sem_init(new_sem, 1, 1);
	arg->queue = q;
	arg->sem = new_sem;
	arg->master_sem = master_sem;
	enqueue(q, new_sem);
	pthread_create(new_activity, NULL, func_pointer, (void*) arg);
	sem_wait(my_sem);

}

void master_thread_func(sem_t* master_sem, struct activity_queue q){
	while(1){
		sem_wait(master_sem);
		if(q.n == 0){
			break;
		}
		sem_t* cur = q.activities[q.head];
		q.head = (q.head + 1) % MAX_ACTIVITY_SIZE;
		q.n--;
		sem_post(cur);
	}
}


void my_activity_function(struct threadarg* args){
	sem_t* my_sem = args->sem;
	sem_t* master_sem = args->master_sem;
	struct activity_queue* q = args->queue;
	int i;
	for(i = 0; i < 100; i++){
		i++;
	}
	yield( my_sem, master_sem, q);

	sem_post(master_sem); //Need to do this at end of function...?
}

int main(int argc, char** argv){

}
